<?php

namespace App\Http\Controllers;

use App\Models\DocumentPelanggan;
use App\Models\KatalogMobil;
use App\Models\User;
use App\Services\TransaksiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(public TransaksiService $transaksiService) {}

    /**
     * Tampilkan halaman checkout 3 langkah untuk mobil yang dipilih.
     */
    public function create(string $slug, Request $request): View|RedirectResponse
    {
        $car = KatalogMobil::with('kategori')
            ->where('slug', $slug)
            ->firstOrFail();

        $start = $request->query('start', now()->addDay()->format('Y-m-d'));
        $end = $request->query('end', now()->addDays(3)->format('Y-m-d'));

        if (! $car->isAvailableForDates($start, $end)) {
            return redirect()->route('catalog.show', ['slug' => $car->slug, 'start' => $start, 'end' => $end])
                ->with('error', 'Maaf, unit ini tidak tersedia pada jadwal tanggal yang dipilih.');
        }

        /** @var User $user */
        $user = Auth::user();
        $document = $user->dokumenPelanggan;

        return view('customer.checkout.create', compact('car', 'start', 'end', 'document'));
    }

    /**
     * Simpan transaksi sewa baru, unggahan dokumen KTP & SIM, serta inisiasi pembayaran.
     */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $car = KatalogMobil::where('slug', $slug)->firstOrFail();

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'start' => ['required', 'date', 'after_or_equal:today'],
            'end' => ['required', 'date', 'after:start'],
            'waktu_ambil' => ['required', 'string'],
            'opsi_pengemudi' => ['required', 'in:Lepas kunci,Dengan sopir'],
            'metode_pengambilan' => ['required', 'string'],
            'titik_jemput' => ['required', 'string', 'max:500'],
            'latitude' => ['nullable', 'string', 'max:50'],
            'longitude' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30'],
            'sim_number' => ['required', 'string', 'max:50'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'versi' => ['nullable', 'integer', 'min:0'],
            'file_ktp' => ['nullable', 'image', 'max:3072'],
            'file_sim' => ['nullable', 'image', 'max:3072'],
        ]);

        if (! $car->isAvailableForDates($validated['start'], $validated['end'])) {
            return back()->with('error', 'Maaf, unit telah dipesan oleh pelanggan lain pada jadwal ini.');
        }

        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'sim_number' => $validated['sim_number'],
            'address' => $validated['titik_jemput'],
        ]);

        $ktpPath = null;
        $simPath = null;
        if ($request->hasFile('file_ktp')) {
            $ktpPath = $request->file('file_ktp')->store('documents', 'local');
        }
        if ($request->hasFile('file_sim')) {
            $simPath = $request->file('file_sim')->store('documents', 'local');
        }

        $doc = DocumentPelanggan::firstOrNew(['user_id' => $user->id]);
        if ($ktpPath) {
            $doc->file_ktp = $ktpPath;
        }
        if ($simPath) {
            $doc->file_sim = $simPath;
        }
        if (! $doc->exists) {
            $doc->status_verifikasi = ($ktpPath && $simPath) ? 'menunggu_validasi' : 'belum_upload';
        } elseif ($ktpPath || $simPath) {
            $doc->status_verifikasi = 'menunggu_validasi';
        }
        $doc->save();

        $transaksi = $this->transaksiService->createBooking($user, $car, $validated);

        return redirect()->route('orders.payment', $transaksi->id_transaksi)
            ->with('success', 'Pesanan '.$transaksi->kode_booking.' berhasil dibuat! Silakan selesaikan pembayaran.');
    }
}

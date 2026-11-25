<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\WalkInTransaksiRequest;
use App\Models\KatalogMobil;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\TransaksiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TransaksiController extends Controller
{
    public function __construct(public TransaksiService $transaksiService) {}

    /**
     * Tampilkan daftar seluruh transaksi sewa dengan filter status dan pencarian.
     */
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));

        $query = Transaksi::with(['user', 'mobil', 'pembayaran'])
            ->orderByDesc('id_transaksi');

        if (in_array($status, ['menunggu_pembayaran', 'dikonfirmasi', 'sedang_disewa', 'selesai', 'dibatalkan'], true)) {
            $query->where('status_pesanan', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('kode_booking', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $user) use ($search): void {
                        $user->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('mobil', function (Builder $mobil) use ($search): void {
                        $mobil->where('plat_nomor', 'like', "%{$search}%")
                            ->orWhere('merk_model', 'like', "%{$search}%");
                    });
            });
        }

        $transaksiList = $query->paginate(15)->withQueryString();

        return view('kasir.transaksi.index', compact('transaksiList'));
    }

    /**
     * Tampilkan formulir pembuatan transaksi walk-in di loket kasir.
     */
    public function create(): View
    {
        $cars = KatalogMobil::with('kategori')
            ->where('status_ketersediaan', 'tersedia')
            ->orderBy('merk_model')
            ->get();

        $customers = User::where('role', 'pelanggan')
            ->orderBy('name')
            ->get();

        return view('kasir.transaksi.create', compact('cars', 'customers'));
    }

    /**
     * Simpan transaksi walk-in baru beserta pembuatan pelanggan otomatis jika belum terdaftar.
     */
    public function store(WalkInTransaksiRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var User $kasir */
        $kasir = $request->user();

        /** @var array{name: string, email: string, password: string}|null $walkInCredentials */
        $walkInCredentials = null;

        $transaksi = DB::transaction(function () use ($validated, $kasir, &$walkInCredentials): Transaksi {
            if (! empty($validated['user_id'])) {
                $customer = User::findOrFail((int) $validated['user_id']);
            } else {
                $temporaryPassword = Str::random(12);

                $customer = User::firstOrCreate(
                    ['email' => strtolower((string) $validated['customer_email'])],
                    [
                        'name' => $validated['customer_name'],
                        'phone' => $validated['customer_phone'],
                        'sim_number' => $validated['sim_number'],
                        'password' => Hash::make($temporaryPassword),
                    ]
                );

                if ($customer->wasRecentlyCreated) {
                    $customer->forceFill(['role' => 'pelanggan'])->save();
                    $walkInCredentials = [
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'password' => $temporaryPassword,
                    ];
                }
            }

            if (empty($customer->sim_number) && ! empty($validated['sim_number'])) {
                $customer->update(['sim_number' => $validated['sim_number']]);
            }

            $car = KatalogMobil::findOrFail((int) $validated['id_mobil']);

            return $this->transaksiService->createBooking($customer, $car, $validated, $kasir->id);
        });

        $redirect = redirect()
            ->route('kasir.transaksi.show', $transaksi->id_transaksi)
            ->with('success', 'Transaksi walk-in '.$transaksi->kode_booking.' berhasil dibuat.');

        if ($walkInCredentials !== null) {
            $redirect->with('walkin_credentials', $walkInCredentials);
        }

        return $redirect;
    }

    /**
     * Tampilkan detail transaksi untuk operasional kasir.
     */
    public function show(int $id): View
    {
        $order = Transaksi::with(['user.dokumenPelanggan', 'mobil.kategori', 'pembayaran.kasir'])
            ->findOrFail($id);

        return view('kasir.transaksi.show', compact('order'));
    }

    /**
     * Catat serah-terima kunci unit kepada pelanggan.
     */
    public function handover(Request $request, int $id): RedirectResponse
    {
        $order = Transaksi::with(['mobil', 'pembayaran'])->findOrFail($id);

        /** @var User $actor */
        $actor = $request->user();

        $this->transaksiService->handoverUnit($order, $actor);

        return back()->with('success', 'Unit armada berhasil diserahterimakan. Status pesanan kini Sedang Disewa.');
    }

    /**
     * Catat pengembalian unit armada dari pelanggan ke pool.
     */
    public function returnUnit(Request $request, int $id): RedirectResponse
    {
        $order = Transaksi::with('mobil')->findOrFail($id);

        /** @var User $actor */
        $actor = $request->user();

        $this->transaksiService->returnUnit($order, $actor);

        return back()->with('success', 'Pengembalian unit berhasil dicatat. Status pesanan kini Selesai.');
    }

    /**
     * Batalkan pesanan dari panel kasir dengan alasan wajib.
     */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate(
            [
                'alasan_batal' => ['required', 'string', 'max:500'],
            ],
            [
                'alasan_batal.required' => 'Alasan pembatalan wajib diisi.',
                'alasan_batal.string' => 'Alasan pembatalan harus berupa teks.',
                'alasan_batal.max' => 'Alasan pembatalan maksimal 500 karakter.',
            ]
        );

        $order = Transaksi::with(['mobil', 'pembayaran'])->findOrFail($id);

        /** @var User $actor */
        $actor = $request->user();

        $this->transaksiService->cancelOrder($order, $validated['alasan_batal'], $actor);

        return back()->with('success', 'Pesanan '.$order->kode_booking.' telah dibatalkan.');
    }

    /**
     * Tampilkan kuitansi transaksi siap cetak.
     */
    public function receipt(int $id): View
    {
        $order = Transaksi::with(['mobil', 'pembayaran.kasir', 'user'])->findOrFail($id);

        return view('customer.orders.receipt', compact('order'));
    }
}

<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Filter status yang tersedia di daftar pembayaran kasir.
     *
     * @var array<string, string>
     */
    public const STATUS_FILTERS = [
        'menunggu_verifikasi' => 'Menunggu verifikasi',
        'belum_dibayar' => 'Belum dibayar',
        'lunas' => 'Lunas',
        'ditolak' => 'Ditolak',
        'semua' => 'Semua',
    ];

    /**
     * Daftar pembayaran dengan filter status, metode, dan pencarian kode booking / pelanggan.
     */
    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), self::STATUS_FILTERS)
            ? (string) $request->query('status')
            : 'menunggu_verifikasi';
        $method = in_array($request->query('metode'), Pembayaran::PAYMENT_METHODS, true)
            ? (string) $request->query('metode')
            : null;
        $search = trim((string) $request->query('q', ''));

        $query = Pembayaran::query()
            ->with(['transaksi.user', 'transaksi.mobil', 'kasir'])
            ->whereHas('transaksi')
            ->when($method, fn (Builder $query, string $method) => $query->where('metode_pembayaran', $method))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('transaksi', function (Builder $transaksi) use ($search): void {
                    $transaksi->where('kode_booking', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            });

        $this->applyStatusFilter($query, $status);

        $payments = ($status === 'menunggu_verifikasi'
                ? $query->orderBy('waktu_bayar')
                : $query->orderByDesc('id_pembayaran'))
            ->paginate(10)
            ->withQueryString();

        $statusCounts = collect(self::STATUS_FILTERS)->mapWithKeys(function (string $label, string $key): array {
            $countQuery = Pembayaran::query()->whereHas('transaksi');
            $this->applyStatusFilter($countQuery, $key);

            return [$key => $countQuery->count()];
        });

        return view('kasir.payments.index', [
            'payments' => $payments,
            'status' => $status,
            'method' => $method,
            'search' => $search,
            'statusFilters' => self::STATUS_FILTERS,
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Detail satu pembayaran beserta data penyewa, unit, dan bukti bayar.
     */
    public function show(int $id): View
    {
        $payment = Pembayaran::with(['transaksi.user.dokumenPelanggan', 'transaksi.mobil.kategori', 'kasir'])
            ->whereHas('transaksi')
            ->findOrFail($id);

        return view('kasir.payments.show', ['payment' => $payment, 'order' => $payment->transaksi]);
    }

    /**
     * Terima pembayaran: tandai lunas dan konfirmasi pesanan.
     */
    public function approve(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'metode_pembayaran' => ['required', Rule::in(Pembayaran::PAYMENT_METHODS)],
            'referensi_pembayaran' => ['nullable', 'string', 'max:100'],
        ]);

        $result = DB::transaction(function () use ($request, $id, $validated): string {
            $payment = Pembayaran::with('transaksi')->lockForUpdate()->findOrFail($id);
            $order = $payment->transaksi;

            if (! $order || $order->status_pesanan !== 'menunggu_pembayaran' || $payment->isPaid()) {
                return 'Pembayaran ini sudah diproses atau pesanannya tidak lagi menunggu pembayaran.';
            }

            $payment->fill([
                'metode_pembayaran' => $validated['metode_pembayaran'],
                'referensi_pembayaran' => $validated['referensi_pembayaran'] ?: ($payment->referensi_pembayaran ?? 'KSR-'.strtoupper(bin2hex(random_bytes(4)))),
                'jumlah_bayar' => $order->total_harga,
                'status_pembayaran' => 'lunas',
                'kasir_id' => $request->user()->id,
                'waktu_bayar' => $payment->waktu_bayar ?? now(),
                'waktu_verifikasi' => now(),
                'catatan_kasir' => null,
            ])->save();

            $order->update(['status_pesanan' => 'dikonfirmasi']);

            return '';
        });

        if ($result !== '') {
            return back()->with('error', $result);
        }

        return redirect()->route('kasir.payments.show', $id)
            ->with('success', 'Pembayaran diterima. Pesanan kini berstatus Dikonfirmasi.');
    }

    /**
     * Tolak konfirmasi pembayaran agar pelanggan mengirim ulang bukti yang benar.
     */
    public function reject(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'catatan_kasir' => ['required', 'string', 'max:500'],
        ]);

        $result = DB::transaction(function () use ($request, $id, $validated): string {
            $payment = Pembayaran::with('transaksi')->lockForUpdate()->findOrFail($id);

            if (! $payment->isAwaitingVerification() || $payment->transaksi?->status_pesanan !== 'menunggu_pembayaran') {
                return 'Hanya pembayaran yang menunggu verifikasi yang dapat ditolak.';
            }

            $payment->fill([
                'status_pembayaran' => 'gagal',
                'kasir_id' => $request->user()->id,
                'waktu_verifikasi' => now(),
                'catatan_kasir' => $validated['catatan_kasir'],
            ])->save();

            return '';
        });

        if ($result !== '') {
            return back()->with('error', $result);
        }

        return redirect()->route('kasir.payments.show', $id)
            ->with('success', 'Pembayaran ditolak. Pelanggan akan diminta mengirim ulang bukti pembayaran.');
    }

    /**
     * Terapkan filter status ke query pembayaran.
     *
     * @param  Builder<Pembayaran>  $query
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'menunggu_verifikasi' => $query->awaitingVerification(),
            'belum_dibayar' => $query->where('status_pembayaran', 'pending')
                ->whereNull('waktu_bayar')
                ->whereHas('transaksi', fn (Builder $transaksi) => $transaksi->where('status_pesanan', 'menunggu_pembayaran')),
            'lunas' => $query->where('status_pembayaran', 'lunas'),
            'ditolak' => $query->where('status_pembayaran', 'gagal'),
            default => $query,
        };
    }
}

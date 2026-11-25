<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\VerifyPembayaranRequest;
use App\Models\Pembayaran;
use App\Models\User;
use App\Services\TransaksiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PembayaranController extends Controller
{
    public function __construct(public TransaksiService $transaksiService) {}

    /**
     * Tampilkan daftar pembayaran dengan filter status, metode, dan kata kunci pencarian.
     */
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $metode = (string) $request->query('metode', '');
        $search = trim((string) $request->query('q', ''));

        $query = Pembayaran::with(['transaksi.user', 'transaksi.mobil', 'kasir'])
            ->orderByDesc('id_pembayaran');

        if (in_array($status, ['pending', 'lunas', 'gagal'], true)) {
            $query->where('status_pembayaran', $status);
        }

        if (in_array($metode, Pembayaran::PAYMENT_METHODS, true)) {
            $query->where('metode_pembayaran', $metode);
        }

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('referensi_pembayaran', 'like', "%{$search}%")
                    ->orWhereHas('transaksi', function (Builder $transaksi) use ($search): void {
                        $transaksi->where('kode_booking', 'like', "%{$search}%")
                            ->orWhereHas('user', function (Builder $user) use ($search): void {
                                $user->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $pembayaranList = $query->paginate(15)->withQueryString();

        return view('kasir.pembayaran.index', [
            'pembayaranList' => $pembayaranList,
        ]);
    }

    /**
     * Verifikasi pembayaran pelanggan (terima atau tolak).
     */
    public function verify(VerifyPembayaranRequest $request, int $id): RedirectResponse
    {
        $pembayaran = Pembayaran::with('transaksi')->findOrFail($id);

        /** @var User $verifier */
        $verifier = $request->user();

        $this->transaksiService->verifyPayment(
            $pembayaran,
            $verifier,
            $request->validated('keputusan') === 'terima',
            $request->validated('catatan')
        );

        $kodeBooking = $pembayaran->transaksi?->kode_booking ?? '#'.$pembayaran->id_transaksi;

        return back()->with('success', 'Status pembayaran pesanan '.$kodeBooking.' berhasil diperbarui.');
    }
}

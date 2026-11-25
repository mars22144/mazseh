<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\ProcessRefundRequest;
use App\Models\Pembayaran;
use App\Models\User;
use App\Services\TransaksiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function __construct(public TransaksiService $transaksiService) {}

    /**
     * Tampilkan antrean dan riwayat pengembalian dana (refund).
     */
    public function index(Request $request): View
    {
        $statusRefund = (string) $request->query('status_refund', '');

        $query = Pembayaran::with(['transaksi.user', 'transaksi.mobil', 'kasir'])
            ->whereIn('status_refund', ['menunggu_proses', 'selesai'])
            ->orderByRaw("CASE WHEN status_refund = 'menunggu_proses' THEN 0 ELSE 1 END")
            ->orderByDesc('id_pembayaran');

        if (in_array($statusRefund, ['menunggu_proses', 'selesai'], true)) {
            $query->where('status_refund', $statusRefund);
        }

        $refundList = $query->paginate(15)->withQueryString();

        return view('kasir.refund.index', compact('refundList'));
    }

    /**
     * Selesaikan pencairan refund untuk pembayaran yang dibatalkan.
     */
    public function process(ProcessRefundRequest $request, int $id): RedirectResponse
    {
        $pembayaran = Pembayaran::with('transaksi')->findOrFail($id);

        /** @var User $actor */
        $actor = $request->user();

        $this->transaksiService->processRefund(
            $pembayaran,
            (float) $request->validated('jumlah_refund'),
            $actor
        );

        $catatan = $request->validated('catatan');
        if ($catatan !== null && $catatan !== '') {
            $pembayaran->update(['catatan_kasir' => $catatan]);
        }

        $kodeBooking = $pembayaran->transaksi?->kode_booking ?? '#'.$pembayaran->id_transaksi;

        return back()->with('success', 'Pengembalian dana (refund) untuk pesanan '.$kodeBooking.' berhasil diselesaikan.');
    }
}

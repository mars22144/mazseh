<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\DocumentPelanggan;
use App\Models\Pembayaran;
use App\Models\Transaksi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Tampilkan ringkasan operasional kasir: statistik harian, antrean verifikasi, dan sewa aktif.
     */
    public function index(): View
    {
        $today = CarbonImmutable::today();

        $stats = [
            'pembayaran_pending' => Pembayaran::where('status_pembayaran', 'pending')->count(),
            'dokumen_pending' => DocumentPelanggan::where('status_verifikasi', 'menunggu_validasi')->count(),
            'refund_pending' => Pembayaran::where('status_refund', 'menunggu_proses')->count(),
            'sedang_disewa' => Transaksi::where('status_pesanan', 'sedang_disewa')->count(),
            'jadwal_ambil_hari_ini' => Transaksi::whereDate('tanggal_mulai', today())
                ->where('status_pesanan', 'dikonfirmasi')
                ->count(),
            'jadwal_kembali_hari_ini' => Transaksi::whereDate('tanggal_selesai', today())
                ->where('status_pesanan', 'sedang_disewa')
                ->count(),
        ];

        $pendingPayments = Pembayaran::with(['transaksi.user', 'transaksi.mobil'])
            ->where('status_pembayaran', 'pending')
            ->orderByDesc('id_pembayaran')
            ->limit(5)
            ->get();

        $activeRentals = Transaksi::with(['user', 'mobil', 'pembayaran'])
            ->whereIn('status_pesanan', ['dikonfirmasi', 'sedang_disewa'])
            ->orderBy('tanggal_mulai')
            ->limit(5)
            ->get();

        $awaitingCount = Pembayaran::awaitingVerification()->count();

        $unpaidCount = Pembayaran::where('status_pembayaran', 'pending')
            ->whereNull('waktu_bayar')
            ->whereHas('transaksi', fn ($query) => $query->where('status_pesanan', 'menunggu_pembayaran'))
            ->count();

        $verifiedToday = Pembayaran::where('status_pembayaran', 'lunas')
            ->whereDate('waktu_verifikasi', $today)
            ->get(['jumlah_bayar']);

        $handovers = Transaksi::with(['mobil', 'user'])
            ->where('status_pesanan', 'dikonfirmasi')
            ->whereBetween('tanggal_mulai', [$today->toDateString(), $today->addDay()->toDateString()])
            ->orderBy('tanggal_mulai')
            ->orderBy('waktu_ambil')
            ->get();

        $queue = Pembayaran::awaitingVerification()
            ->with(['transaksi.user', 'transaksi.mobil'])
            ->orderBy('waktu_bayar')
            ->limit(5)
            ->get();

        return view('kasir.dashboard', [
            'stats' => $stats,
            'pendingPayments' => $pendingPayments,
            'activeRentals' => $activeRentals,
            'awaitingCount' => $awaitingCount,
            'unpaidCount' => $unpaidCount,
            'receivedTodayTotal' => (float) $verifiedToday->sum('jumlah_bayar'),
            'receivedTodayCount' => $verifiedToday->count(),
            'handoversTodayCount' => $handovers->filter(fn (Transaksi $order): bool => $order->tanggal_mulai->isSameDay($today))->count(),
            'handovers' => $handovers,
            'queue' => $queue,
            'dailyIncome' => $this->dailyIncome($today),
        ]);
    }

    /**
     * Kas masuk (pembayaran lunas) per hari selama 7 hari terakhir.
     *
     * @return Collection<int, array{label: string, total: float, is_today: bool}>
     */
    private function dailyIncome(CarbonImmutable $today): Collection
    {
        $start = $today->subDays(6);

        $totals = Pembayaran::where('status_pembayaran', 'lunas')
            ->whereRaw('DATE(COALESCE(waktu_verifikasi, waktu_bayar)) >= ?', [$start->toDateString()])
            ->selectRaw('DATE(COALESCE(waktu_verifikasi, waktu_bayar)) as tanggal, SUM(jumlah_bayar) as total')
            ->groupBy('tanggal')
            ->pluck('total', 'tanggal');

        return collect(range(0, 6))->map(function (int $offset) use ($start, $today, $totals): array {
            $day = $start->addDays($offset);

            return [
                'label' => $day->translatedFormat('D'),
                'total' => (float) ($totals[$day->toDateString()] ?? 0),
                'is_today' => $day->isSameDay($today),
            ];
        });
    }
}

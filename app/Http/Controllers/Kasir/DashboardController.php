<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Transaksi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Tampilkan ringkasan kerja kasir: antrean verifikasi, kas masuk, dan jadwal serah-terima.
     */
    public function index(): View
    {
        $today = CarbonImmutable::today();

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

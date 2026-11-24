<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentPelanggan;
use App\Models\KatalogMobil;
use App\Models\Pembayaran;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Ikhtisar bisnis: pendapatan bulan ini, pesanan aktif, kondisi armada, dan pengguna.
     */
    public function index(): View
    {
        $monthStart = now()->startOfMonth();

        $monthlyRevenue = (float) Pembayaran::where('status_pembayaran', 'lunas')
            ->whereRaw('COALESCE(waktu_verifikasi, waktu_bayar) >= ?', [$monthStart])
            ->sum('jumlah_bayar');

        $fleetByStatus = KatalogMobil::selectRaw('status_ketersediaan, COUNT(*) as total')
            ->groupBy('status_ketersediaan')
            ->pluck('total', 'status_ketersediaan');

        return view('admin.dashboard', [
            'monthlyRevenue' => $monthlyRevenue,
            'activeOrders' => Transaksi::where('status_pesanan', 'dikonfirmasi')
                ->whereDate('tanggal_selesai', '>=', today())
                ->count(),
            'awaitingPayments' => Pembayaran::awaitingVerification()->count(),
            'customerCount' => User::where('role', 'pelanggan')->count(),
            'pendingDocuments' => DocumentPelanggan::where('status_verifikasi', 'menunggu_validasi')->count(),
            'fleetByStatus' => $fleetByStatus,
            'fleetTotal' => (int) $fleetByStatus->sum(),
            'recentOrders' => Transaksi::with(['user', 'mobil', 'pembayaran'])
                ->orderByDesc('id_transaksi')
                ->limit(6)
                ->get(),
        ]);
    }
}

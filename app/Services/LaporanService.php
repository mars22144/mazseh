<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Pemeliharaan;
use App\Models\Transaksi;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanService
{
    /**
     * Hitung ringkasan keuangan dan volume transaksi pada rentang tanggal opsional.
     *
     * @return array{
     *     pendapatan_kotor: float,
     *     total_refund: float,
     *     pendapatan_bersih: float,
     *     biaya_pemeliharaan: float,
     *     laba_bersih: float,
     *     total_transaksi: int,
     *     transaksi_selesai: int,
     *     transaksi_dibatalkan: int
     * }
     */
    public function getFinancialSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $paidQuery = Pembayaran::query()->where('status_pembayaran', 'lunas');
        $refundQuery = Pembayaran::query()->where('status_refund', 'selesai');
        $maintenanceQuery = Pemeliharaan::query();
        $transaksiQuery = Transaksi::query();

        if ($startDate !== null && $startDate !== '') {
            $paidQuery->whereDate('waktu_bayar', '>=', $startDate);
            $refundQuery->whereDate('waktu_bayar', '>=', $startDate);
            $maintenanceQuery->whereDate('tanggal_masuk', '>=', $startDate);
            $transaksiQuery->whereDate('waktu_pesan', '>=', $startDate);
        }

        if ($endDate !== null && $endDate !== '') {
            $paidQuery->whereDate('waktu_bayar', '<=', $endDate);
            $refundQuery->whereDate('waktu_bayar', '<=', $endDate);
            $maintenanceQuery->whereDate('tanggal_masuk', '<=', $endDate);
            $transaksiQuery->whereDate('waktu_pesan', '<=', $endDate);
        }

        $pendapatanKotor = (float) $paidQuery->sum('jumlah_bayar');
        $totalRefund = (float) $refundQuery->sum('jumlah_refund');
        $pendapatanBersih = $pendapatanKotor - $totalRefund;
        $biayaPemeliharaan = (float) $maintenanceQuery->sum('total_biaya');
        $labaBersih = $pendapatanBersih - $biayaPemeliharaan;

        $totalTransaksi = (clone $transaksiQuery)->count();
        $transaksiSelesai = (clone $transaksiQuery)
            ->whereIn('status_pesanan', ['dikonfirmasi', 'sedang_disewa', 'selesai'])
            ->count();
        $transaksiDibatalkan = (clone $transaksiQuery)
            ->where('status_pesanan', 'dibatalkan')
            ->count();

        return [
            'pendapatan_kotor' => $pendapatanKotor,
            'total_refund' => $totalRefund,
            'pendapatan_bersih' => $pendapatanBersih,
            'biaya_pemeliharaan' => $biayaPemeliharaan,
            'laba_bersih' => $labaBersih,
            'total_transaksi' => $totalTransaksi,
            'transaksi_selesai' => $transaksiSelesai,
            'transaksi_dibatalkan' => $transaksiDibatalkan,
        ];
    }

    /**
     * Hasilkan unduhan laporan transaksi dan keuangan dalam format CSV.
     */
    public function streamCsvReport(?string $startDate = null, ?string $endDate = null): StreamedResponse
    {
        $filename = 'laporan-keuangan-mazseh-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($startDate, $endDate): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Kode Booking',
                'Pelanggan',
                'Mobil',
                'Tanggal Mulai',
                'Tanggal Selesai',
                'Status Pesanan',
                'Status Pembayaran',
                'Jumlah Bayar',
                'Jumlah Refund',
                'Pendapatan Bersih',
            ]);

            $query = Transaksi::with(['user', 'mobil', 'pembayaran'])
                ->orderByDesc('waktu_pesan');

            if ($startDate !== null && $startDate !== '') {
                $query->whereDate('waktu_pesan', '>=', $startDate);
            }

            if ($endDate !== null && $endDate !== '') {
                $query->whereDate('waktu_pesan', '<=', $endDate);
            }

            $query->chunk(200, function ($transactions) use ($handle): void {
                foreach ($transactions as $transaksi) {
                    $pembayaran = $transaksi->pembayaran;
                    $jumlahBayar = $pembayaran?->status_pembayaran === 'lunas'
                        ? (float) $pembayaran->jumlah_bayar
                        : 0.0;
                    $jumlahRefund = $pembayaran?->status_refund === 'selesai'
                        ? (float) $pembayaran->jumlah_refund
                        : 0.0;
                    $pendapatanBersih = $jumlahBayar - $jumlahRefund;

                    fputcsv($handle, [
                        $this->sanitizeCsvCell((string) ($transaksi->kode_booking ?? '#'.$transaksi->id_transaksi)),
                        $this->sanitizeCsvCell((string) ($transaksi->user?->name ?? '-')),
                        $this->sanitizeCsvCell((string) ($transaksi->mobil?->merk_model ?? '-')),
                        $transaksi->tanggal_mulai?->format('Y-m-d') ?? '-',
                        $transaksi->tanggal_selesai?->format('Y-m-d') ?? '-',
                        $transaksi->status_pesanan,
                        $pembayaran?->status_pembayaran ?? 'pending',
                        number_format($jumlahBayar, 2, '.', ''),
                        number_format($jumlahRefund, 2, '.', ''),
                        number_format($pendapatanBersih, 2, '.', ''),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Cegah CSV formula injection pada sel teks.
     */
    private function sanitizeCsvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}

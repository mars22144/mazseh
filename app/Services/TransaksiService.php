<?php

namespace App\Services;

use App\Models\KatalogMobil;
use App\Models\Pembayaran;
use App\Models\Transaksi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransaksiService
{
    public function __construct(public ActivityLogService $activityLog) {}

    /**
     * Buat pesanan sewa dan record pembayaran awal secara atomik dengan Optimistic Locking.
     *
     * @param  array{
     *     start: string,
     *     end: string,
     *     waktu_ambil?: string|null,
     *     opsi_pengemudi?: string|null,
     *     metode_pengambilan?: string|null,
     *     titik_jemput?: string|null,
     *     latitude?: string|null,
     *     longitude?: string|null,
     *     catatan?: string|null,
     *     metode_pembayaran?: string|null,
     *     referensi_pembayaran?: string|null,
     *     versi?: int|string|null,
     *     langsung_lunas?: bool|int|string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function createBooking(User $user, KatalogMobil $car, array $data, ?int $kasirId = null): Transaksi
    {
        return DB::transaction(function () use ($user, $car, $data, $kasirId): Transaksi {
            $currentCar = KatalogMobil::where('id_mobil', $car->id_mobil)->firstOrFail();

            if (! $currentCar->isAvailableForDates((string) $data['start'], (string) $data['end'])) {
                throw ValidationException::withMessages([
                    'start' => 'Maaf, unit tidak tersedia pada rentang tanggal tersebut.',
                ]);
            }

            $expectedVersion = (int) ($data['versi'] ?? $currentCar->versi);
            $updatedRows = KatalogMobil::where('id_mobil', $currentCar->id_mobil)
                ->where('versi', $expectedVersion)
                ->update(['versi' => $expectedVersion + 1]);

            if ($updatedRows === 0) {
                throw ValidationException::withMessages([
                    'mobil' => 'Data armada baru saja diperbarui oleh transaksi lain. Silakan coba lagi.',
                ]);
            }

            $startDate = Carbon::parse((string) $data['start']);
            $endDate = Carbon::parse((string) $data['end']);
            $days = max(1, (int) $startDate->diffInDays($endDate));
            $opsiPengemudi = (string) ($data['opsi_pengemudi'] ?? 'Lepas kunci');
            $driverFee = $opsiPengemudi === 'Dengan sopir' ? 200000 : 0;
            $totalHarga = ((float) $currentCar->harga_sewa + $driverFee) * $days;

            do {
                $kodeBooking = 'MZS-'.strtoupper(Str::random(6));
            } while (Transaksi::where('kode_booking', $kodeBooking)->exists());

            $langsungLunas = ! empty($data['langsung_lunas']);
            $statusPesanan = $langsungLunas ? 'dikonfirmasi' : 'menunggu_pembayaran';
            $statusPembayaran = $langsungLunas ? 'lunas' : 'pending';

            $transaksi = Transaksi::create([
                'user_id' => $user->id,
                'id_mobil' => $currentCar->id_mobil,
                'kode_booking' => $kodeBooking,
                'tanggal_mulai' => $data['start'],
                'tanggal_selesai' => $data['end'],
                'waktu_ambil' => $data['waktu_ambil'] ?? '09:00',
                'opsi_pengemudi' => $opsiPengemudi,
                'metode_pengambilan' => $data['metode_pengambilan'] ?? 'Ambil di pool MAZSEH',
                'titik_jemput' => $data['titik_jemput'] ?? 'Pool MAZSEH Klaten',
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'total_harga' => $totalHarga,
                'status_pesanan' => $statusPesanan,
                'catatan' => $data['catatan'] ?? null,
                'waktu_pesan' => now(),
            ]);

            Pembayaran::create([
                'id_transaksi' => $transaksi->id_transaksi,
                'kasir_id' => $langsungLunas ? $kasirId : null,
                'jumlah_bayar' => $totalHarga,
                'status_pembayaran' => $statusPembayaran,
                'metode_pembayaran' => $data['metode_pembayaran'] ?? 'Transfer bank',
                'referensi_pembayaran' => $data['referensi_pembayaran'] ?? null,
                'waktu_bayar' => $langsungLunas ? now() : null,
                'waktu_verifikasi' => $langsungLunas ? now() : null,
            ]);

            $this->activityLog->log(
                'transaksi',
                'INSERT',
                "Membuat pesanan {$kodeBooking} untuk unit {$currentCar->merk_model} ({$currentCar->plat_nomor}).",
                $kasirId ?? $user->id
            );

            return $transaksi->load(['mobil', 'pembayaran', 'user']);
        });
    }

    /**
     * Verifikasi pembayaran pelanggan (setujui atau tolak) secara atomik.
     */
    public function verifyPayment(Pembayaran $pembayaran, User $verifier, bool $disetujui, ?string $catatan = null): Pembayaran
    {
        $updatedPayment = DB::transaction(function () use ($pembayaran, $verifier, $disetujui, $catatan): Pembayaran {
            $lockedPayment = Pembayaran::with('transaksi')->lockForUpdate()->findOrFail($pembayaran->id_pembayaran);
            $kodeBooking = $lockedPayment->transaksi?->kode_booking ?? '#'.$lockedPayment->id_transaksi;

            if ($disetujui === true) {
                $lockedPayment->status_pembayaran = 'lunas';
                $lockedPayment->kasir_id = $verifier->id;
                $lockedPayment->waktu_bayar = $lockedPayment->waktu_bayar ?? now();
                $lockedPayment->waktu_verifikasi = now();
                $lockedPayment->catatan_kasir = $catatan;
                $lockedPayment->save();

                if ($lockedPayment->transaksi && $lockedPayment->transaksi->status_pesanan === 'menunggu_pembayaran') {
                    $lockedPayment->transaksi->update(['status_pesanan' => 'dikonfirmasi']);
                }

                $suffix = $catatan ? " ({$catatan})" : '';
                $keterangan = "Menyetujui pembayaran transaksi {$kodeBooking} sebesar {$lockedPayment->formatted_amount}{$suffix}.";
            } else {
                $lockedPayment->status_pembayaran = 'gagal';
                $lockedPayment->kasir_id = $verifier->id;
                $lockedPayment->waktu_verifikasi = now();
                $lockedPayment->catatan_kasir = $catatan;
                $lockedPayment->save();

                $suffix = $catatan ? ": {$catatan}" : '.';
                $keterangan = "Menolak pembayaran transaksi {$kodeBooking}{$suffix}";
            }

            $this->activityLog->log('pembayaran', 'UPDATE', $keterangan, $verifier->id);

            return $lockedPayment->fresh(['transaksi', 'kasir']);
        });

        $pembayaran->refresh();

        return $updatedPayment;
    }

    /**
     * Proses serah-terima kunci unit kepada pelanggan.
     *
     * @throws ValidationException
     */
    public function handoverUnit(Transaksi $transaksi, User $actor): Transaksi
    {
        $updatedOrder = DB::transaction(function () use ($transaksi, $actor): Transaksi {
            $order = Transaksi::with(['pembayaran', 'mobil'])->lockForUpdate()->findOrFail($transaksi->id_transaksi);

            if ($order->status_pesanan !== 'dikonfirmasi' || $order->pembayaran?->status_pembayaran !== 'lunas') {
                throw ValidationException::withMessages([
                    'transaksi' => 'Serah-terima unit hanya dapat dilakukan untuk pesanan yang sudah dikonfirmasi dan lunas.',
                ]);
            }

            $order->update(['status_pesanan' => 'sedang_disewa']);

            if ($order->mobil) {
                $order->mobil->update([
                    'status_ketersediaan' => 'disewakan',
                    'versi' => $order->mobil->versi + 1,
                ]);
            }

            $this->activityLog->log(
                'transaksi',
                'UPDATE',
                "Serah-terima unit {$order->mobil?->merk_model} untuk pesanan {$order->kode_booking}.",
                $actor->id
            );

            return $order->fresh(['mobil', 'pembayaran', 'user']);
        });

        $transaksi->refresh();

        return $updatedOrder;
    }

    /**
     * Proses pengembalian unit dari pelanggan ke pool.
     *
     * @throws ValidationException
     */
    public function returnUnit(Transaksi $transaksi, User $actor): Transaksi
    {
        $updatedOrder = DB::transaction(function () use ($transaksi, $actor): Transaksi {
            $order = Transaksi::with(['mobil', 'pembayaran'])->lockForUpdate()->findOrFail($transaksi->id_transaksi);

            if ($order->status_pesanan !== 'sedang_disewa') {
                throw ValidationException::withMessages([
                    'transaksi' => 'Pengembalian unit hanya dapat dilakukan untuk pesanan yang berstatus sedang disewa.',
                ]);
            }

            $order->update(['status_pesanan' => 'selesai']);

            if ($order->mobil) {
                $order->mobil->update([
                    'status_ketersediaan' => 'tersedia',
                    'versi' => $order->mobil->versi + 1,
                ]);
            }

            $this->activityLog->log(
                'transaksi',
                'UPDATE',
                "Pengembalian unit {$order->mobil?->merk_model} untuk pesanan {$order->kode_booking}.",
                $actor->id
            );

            return $order->fresh(['mobil', 'pembayaran', 'user']);
        });

        $transaksi->refresh();

        return $updatedOrder;
    }

    /**
     * Batalkan pesanan dan aktifkan status refund otomatis bila pembayaran sudah lunas.
     *
     * @throws ValidationException
     */
    public function cancelOrder(Transaksi $transaksi, string $alasanBatal, ?User $actor = null): Transaksi
    {
        $updatedOrder = DB::transaction(function () use ($transaksi, $alasanBatal, $actor): Transaksi {
            $order = Transaksi::with(['mobil', 'pembayaran'])->lockForUpdate()->findOrFail($transaksi->id_transaksi);

            if (in_array($order->status_pesanan, ['selesai', 'dibatalkan'], true)) {
                throw ValidationException::withMessages([
                    'status_pesanan' => 'Pesanan yang sudah selesai atau dibatalkan tidak dapat dibatalkan kembali.',
                ]);
            }

            if ($order->status_pesanan === 'sedang_disewa' && $order->mobil) {
                $order->mobil->update([
                    'status_ketersediaan' => 'tersedia',
                    'versi' => $order->mobil->versi + 1,
                ]);
            }

            $order->update([
                'status_pesanan' => 'dibatalkan',
                'alasan_batal' => $alasanBatal,
            ]);

            if ($order->pembayaran && $order->pembayaran->status_pembayaran === 'lunas') {
                $order->pembayaran->update([
                    'status_refund' => 'menunggu_proses',
                ]);
            }

            $this->activityLog->log(
                'transaksi',
                'UPDATE',
                "Membatalkan pesanan {$order->kode_booking}. Alasan: {$alasanBatal}",
                $actor?->id
            );

            return $order->fresh(['mobil', 'pembayaran', 'user']);
        });

        $transaksi->refresh();

        return $updatedOrder;
    }

    /**
     * Proses pencairan refund untuk pembayaran yang berstatus menunggu proses refund.
     *
     * @throws ValidationException
     */
    public function processRefund(Pembayaran $pembayaran, float $jumlahRefund, User $actor): Pembayaran
    {
        $updatedPayment = DB::transaction(function () use ($pembayaran, $jumlahRefund, $actor): Pembayaran {
            $payment = Pembayaran::with('transaksi')->lockForUpdate()->findOrFail($pembayaran->id_pembayaran);

            if ($payment->status_refund !== 'menunggu_proses') {
                throw ValidationException::withMessages([
                    'status_refund' => 'Pembayaran ini tidak sedang menunggu proses refund.',
                ]);
            }

            if ($jumlahRefund <= 0 || $jumlahRefund > (float) $payment->jumlah_bayar) {
                throw ValidationException::withMessages([
                    'jumlah_refund' => 'Nominal refund harus lebih dari 0 dan tidak melebihi jumlah pembayaran.',
                ]);
            }

            $payment->update([
                'jumlah_refund' => $jumlahRefund,
                'status_refund' => 'selesai',
                'kasir_id' => $actor->id,
            ]);

            $kodeBooking = $payment->transaksi?->kode_booking ?? '#'.$payment->id_transaksi;
            $formattedRefund = 'Rp'.number_format($jumlahRefund, 0, ',', '.');

            $this->activityLog->log(
                'pembayaran',
                'UPDATE',
                "Memproses refund sebesar {$formattedRefund} untuk pesanan {$kodeBooking}.",
                $actor->id
            );

            return $payment->fresh(['transaksi', 'kasir']);
        });

        $pembayaran->refresh();

        return $updatedPayment;
    }
}

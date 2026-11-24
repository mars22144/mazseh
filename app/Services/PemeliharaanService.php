<?php

namespace App\Services;

use App\Models\KatalogMobil;
use App\Models\Pemeliharaan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PemeliharaanService
{
    public function __construct(public ActivityLogService $activityLog) {}

    /**
     * Catat jadwal pemeliharaan bengkel baru dan sinkronkan status ketersediaan unit.
     *
     * @param  array{
     *     id_mobil: int|string,
     *     tanggal_masuk: string,
     *     tanggal_selesai?: string|null,
     *     jenis_perawatan: string,
     *     total_biaya?: float|int|string|null,
     *     keterangan_bengkel?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function createMaintenance(array $data, User $actor): Pemeliharaan
    {
        return DB::transaction(function () use ($data, $actor): Pemeliharaan {
            $car = KatalogMobil::lockForUpdate()->findOrFail($data['id_mobil']);

            if ($car->status_ketersediaan === 'disewakan') {
                throw ValidationException::withMessages([
                    'id_mobil' => 'Unit sedang disewa dan belum dapat dimasukkan ke bengkel.',
                ]);
            }

            $pemeliharaan = Pemeliharaan::create([
                'id_mobil' => $car->id_mobil,
                'tanggal_masuk' => $data['tanggal_masuk'],
                'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
                'jenis_perawatan' => $data['jenis_perawatan'],
                'total_biaya' => $data['total_biaya'] ?? 0,
                'keterangan_bengkel' => $data['keterangan_bengkel'] ?? null,
            ]);

            if (empty($data['tanggal_selesai'])) {
                $car->update([
                    'status_ketersediaan' => 'maintenance',
                    'versi' => $car->versi + 1,
                ]);
            }

            $this->activityLog->log(
                'pemeliharaan',
                'INSERT',
                "Mencatat pemeliharaan {$pemeliharaan->jenis_perawatan} untuk unit {$car->merk_model} ({$car->plat_nomor}).",
                $actor->id
            );

            return $pemeliharaan->load('mobil');
        });
    }

    /**
     * Selesaikan catatan pemeliharaan dan kembalikan status unit menjadi tersedia bila tidak ada servis aktif lainnya.
     */
    public function completeMaintenance(
        Pemeliharaan $pemeliharaan,
        string $tanggalSelesai,
        ?float $totalBiaya,
        ?string $keterangan,
        User $actor
    ): Pemeliharaan {
        $updatedRecord = DB::transaction(function () use ($pemeliharaan, $tanggalSelesai, $totalBiaya, $keterangan, $actor): Pemeliharaan {
            $record = Pemeliharaan::with('mobil')->lockForUpdate()->findOrFail($pemeliharaan->id_pemeliharaan);

            $updates = [
                'tanggal_selesai' => $tanggalSelesai,
            ];

            if ($totalBiaya !== null) {
                $updates['total_biaya'] = $totalBiaya;
            }

            if ($keterangan !== null) {
                $updates['keterangan_bengkel'] = $keterangan;
            }

            $record->update($updates);

            $hasRemainingActiveMaintenance = Pemeliharaan::where('id_mobil', $record->id_mobil)
                ->whereNull('tanggal_selesai')
                ->exists();

            if (! $hasRemainingActiveMaintenance && $record->mobil) {
                $record->mobil->update([
                    'status_ketersediaan' => 'tersedia',
                    'versi' => $record->mobil->versi + 1,
                ]);
            }

            $this->activityLog->log(
                'pemeliharaan',
                'UPDATE',
                "Menyelesaikan pemeliharaan {$record->jenis_perawatan} untuk unit {$record->mobil?->merk_model} ({$record->mobil?->plat_nomor}).",
                $actor->id
            );

            return $record->fresh('mobil');
        });

        $pemeliharaan->refresh();

        return $updatedRecord;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pemeliharaan extends Model
{
    use HasFactory;

    protected $table = 'pemeliharaan';

    protected $primaryKey = 'id_pemeliharaan';

    public $timestamps = false;

    protected $fillable = [
        'id_mobil',
        'tanggal_masuk',
        'tanggal_selesai',
        'jenis_perawatan',
        'total_biaya',
        'keterangan_bengkel',
    ];

    /**
     * Definisi cast atribut pemeliharaan.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'tanggal_selesai' => 'date',
            'total_biaya' => 'decimal:2',
        ];
    }

    /**
     * Relasi ke unit mobil yang sedang/pernah diservis.
     */
    public function mobil(): BelongsTo
    {
        return $this->belongsTo(KatalogMobil::class, 'id_mobil', 'id_mobil');
    }

    /**
     * Format total biaya pemeliharaan ke mata uang Rupiah.
     */
    public function getFormattedCostAttribute(): string
    {
        return 'Rp'.number_format((float) $this->total_biaya, 0, ',', '.');
    }

    /**
     * Cek apakah catatan pemeliharaan sudah selesai.
     */
    public function isCompleted(): bool
    {
        return $this->tanggal_selesai !== null;
    }
}

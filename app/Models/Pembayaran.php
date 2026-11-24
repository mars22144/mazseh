<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembayaran';

    protected $primaryKey = 'id_pembayaran';

    public $timestamps = false;

    /**
     * Metode pembayaran yang dilayani MAZSEH.
     *
     * @var list<string>
     */
    public const PAYMENT_METHODS = ['Transfer bank', 'QRIS', 'Tunai di kasir'];

    protected $fillable = [
        'id_transaksi',
        'kasir_id',
        'jumlah_bayar',
        'status_pembayaran',
        'jumlah_refund',
        'status_refund',
        'waktu_bayar',
        'metode_pembayaran',
        'referensi_pembayaran',
        'bukti_bayar',
        'waktu_verifikasi',
        'catatan_kasir',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_bayar' => 'decimal:2',
            'jumlah_refund' => 'decimal:2',
            'waktu_bayar' => 'datetime',
            'waktu_verifikasi' => 'datetime',
        ];
    }

    /**
     * Relasi ke transaksi yang dibayarkan.
     */
    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    /**
     * Relasi ke staf kasir yang memvalidasi pembayaran.
     */
    public function kasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kasir_id', 'id');
    }

    /**
     * Format nominal pembayaran ke Rupiah.
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp'.number_format((float) $this->jumlah_bayar, 0, ',', '.');
    }

    /**
     * Batasi query ke pembayaran yang sudah dikirim pelanggan dan menunggu verifikasi kasir.
     */
    public function scopeAwaitingVerification(Builder $query): Builder
    {
        return $query->where('status_pembayaran', 'pending')
            ->whereNotNull('waktu_bayar')
            ->whereHas('transaksi', fn (Builder $transaksi) => $transaksi->where('status_pesanan', 'menunggu_pembayaran'));
    }

    /**
     * Pembayaran lunas yang sudah diterima.
     */
    public function isPaid(): bool
    {
        return $this->status_pembayaran === 'lunas';
    }

    /**
     * Pelanggan sudah mengirim konfirmasi, tinggal menunggu verifikasi kasir.
     */
    public function isAwaitingVerification(): bool
    {
        return $this->status_pembayaran === 'pending' && $this->waktu_bayar !== null;
    }

    /**
     * Pembayaran ditolak kasir dan perlu dikirim ulang oleh pelanggan.
     */
    public function isRejected(): bool
    {
        return $this->status_pembayaran === 'gagal';
    }

    /**
     * Nominal yang benar-benar sudah diterima (hanya dihitung bila lunas).
     */
    public function getAmountReceivedAttribute(): float
    {
        return $this->isPaid() ? (float) $this->jumlah_bayar : 0.0;
    }

    /**
     * Label status pembayaran.
     */
    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            $this->isPaid() => 'Lunas',
            $this->isRejected() => 'Ditolak',
            $this->isAwaitingVerification() => 'Menunggu Verifikasi',
            $this->status_pembayaran === 'pending' => 'Belum Dibayar',
            default => ucfirst((string) $this->status_pembayaran),
        };
    }

    /**
     * Warna badge status pembayaran.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match (true) {
            $this->isPaid() => 'green',
            $this->isRejected() => 'red',
            $this->isAwaitingVerification() => 'blue',
            default => 'amber',
        };
    }
}

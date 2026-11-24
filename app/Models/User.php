<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'city', 'address', 'sim_number'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Cek apakah pengguna memiliki salah satu peran yang diberikan.
     */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Cek apakah pengguna merupakan staf yang boleh membuka panel kasir.
     */
    public function canAccessKasirPanel(): bool
    {
        return $this->hasRole('kasir', 'admin');
    }

    /**
     * Inisial nama untuk avatar (maksimal dua huruf).
     */
    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return strtoupper(collect($words)->take(2)->map(fn (string $word): string => mb_substr($word, 0, 1))->implode(''));
    }

    /**
     * Label peran yang ramah pengguna.
     */
    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Admin',
            'kasir' => 'Kasir',
            default => 'Pelanggan',
        };
    }

    /**
     * Relasi ke dokumen pelanggan (KTP & SIM).
     */
    public function dokumenPelanggan(): HasOne
    {
        return $this->hasOne(DocumentPelanggan::class, 'user_id', 'id');
    }

    /**
     * Relasi ke daftar transaksi sewa mobil.
     */
    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'user_id', 'id');
    }

    /**
     * Relasi ke sesi chat AI.
     */
    public function sesiChatAi(): HasMany
    {
        return $this->hasMany(SesiChatAi::class, 'user_id', 'id');
    }
}

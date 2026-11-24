<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogActivity extends Model
{
    protected $table = 'log_activity';

    protected $primaryKey = 'id_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tabel_terdampak',
        'jenis_aksi',
        'keterangan',
        'ip_address',
        'waktu_kejadian',
    ];

    protected function casts(): array
    {
        return [
            'waktu_kejadian' => 'datetime',
        ];
    }

    /**
     * Catat jejak audit aksi staf (siapa, apa, kapan, dari IP mana).
     *
     * @param  'INSERT'|'UPDATE'|'DELETE'|'LOGIN_FAILED'|'SQLI_ATTEMPT'  $action
     */
    public static function record(string $table, string $action, string $description): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'tabel_terdampak' => $table,
            'jenis_aksi' => $action,
            'keterangan' => mb_substr($description, 0, 1000),
            'ip_address' => request()->ip(),
            'waktu_kejadian' => now(),
        ]);
    }

    /**
     * Relasi ke pengguna pelaku aksi.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

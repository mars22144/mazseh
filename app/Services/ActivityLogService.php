<?php

namespace App\Services;

use App\Models\LogActivity;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * Catat jejak audit ke tabel log_activity.
     *
     * @param  'INSERT'|'UPDATE'|'DELETE'|'LOGIN_FAILED'|'SQLI_ATTEMPT'  $jenisAksi
     */
    public function log(
        string $tabelTerdampak,
        string $jenisAksi,
        string $keterangan,
        ?int $userId = null,
        ?string $ipAddress = null
    ): LogActivity {
        return LogActivity::create([
            'user_id' => $userId ?? Auth::id(),
            'tabel_terdampak' => $tabelTerdampak,
            'jenis_aksi' => $jenisAksi,
            'keterangan' => mb_substr($keterangan, 0, 1000),
            'ip_address' => $ipAddress ?? request()?->ip(),
            'waktu_kejadian' => now(),
        ]);
    }

    /**
     * Deteksi pola dasar percobaan SQL Injection pada input teks.
     */
    public function containsSqlInjectionPattern(string $input): bool
    {
        return (bool) preg_match('/(\b(UNION|SELECT|INSERT|UPDATE|DELETE|DROP)\b|--|\/\*|\'\s*OR\s*\'|\'\s*=\s*\')/i', $input);
    }
}

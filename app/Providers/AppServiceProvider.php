<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lapis kedua login: batasi maksimal 20 percobaan per menit per IP
        // (lebih tinggi dari batas 5x per email+IP di LoginRequest agar pesan validasi lokal tetap muncul lebih dulu)
        RateLimiter::for('login-ip', function (Request $request): Limit {
            return Limit::perMinute(20)->by((string) $request->ip());
        });

        // Batasi pendaftaran akun baru maksimal 5 kali per menit per IP
        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        // Batasi permintaan tautan reset kata sandi maksimal 5 kali per menit per IP
        RateLimiter::for('forgot-password', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->ip());
        });
    }
}

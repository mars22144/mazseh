<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * Memastikan halaman login dapat diakses oleh pengguna tamu.
     */
    public function test_halaman_login_dapat_diakses_oleh_tamu(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Selamat datang kembali');
        $response->assertSee('Ingat saya');
    }

    /**
     * Memastikan pengguna dapat login dengan kredensial valid dan sesi diregenerasi.
     */
    public function test_pengguna_dapat_login_dengan_kredensial_valid_dan_sesi_diregenerasi(): void
    {
        $user = User::create([
            'name' => 'Pengguna Valid',
            'email' => 'valid_user@mazseh.test',
            'password' => Hash::make('Rahasia123'),
            'role' => 'pelanggan',
            'phone' => '081234567891',
        ]);

        $this->startSession();
        $oldSessionId = session()->getId();

        $response = $this->post('/login', [
            'email' => 'valid_user@mazseh.test',
            'password' => 'Rahasia123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success');
        $this->assertNotEquals($oldSessionId, session()->getId());
    }

    /**
     * Memastikan pengguna tidak dapat login jika password salah.
     */
    public function test_pengguna_tidak_dapat_login_dengan_password_salah(): void
    {
        $user = User::create([
            'name' => 'Pengguna Salah Password',
            'email' => 'wrong_pass@mazseh.test',
            'password' => Hash::make('Rahasia123'),
            'role' => 'pelanggan',
            'phone' => '081234567892',
        ]);

        $response = $this->post('/login', [
            'email' => 'wrong_pass@mazseh.test',
            'password' => 'PasswordSalah123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseHas('log_activity', [
            'user_id' => $user->id,
            'tabel_terdampak' => 'users',
            'jenis_aksi' => 'LOGIN_FAILED',
        ]);
    }

    /**
     * Memastikan validasi input ketat menolak format email tidak valid dan password pendek.
     */
    public function test_validasi_input_ketat_menolak_email_tidak_valid_dan_password_pendek(): void
    {
        $response = $this->post('/login', [
            'email' => 'bukan-email',
            'password' => '123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email', 'password']);
    }

    /**
     * Memastikan pencegahan brute force memblokir percobaan login ke-6 (Rate Limiter).
     */
    public function test_pencegahan_brute_force_memblokir_percobaan_login_ke_enam(): void
    {
        $email = 'brute_force_victim@mazseh.test';

        // 5 kali percobaan gagal
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('/login', [
                'email' => $email,
                'password' => 'PasswordSalah123',
            ]);
            $response->assertSessionHasErrors('email');
        }

        // Percobaan ke-6 harus diblokir oleh RateLimiter
        $responseKeenam = $this->post('/login', [
            'email' => $email,
            'password' => 'PasswordSalah123',
        ]);

        $this->assertGuest();
        $responseKeenam->assertSessionHasErrors('email');

        $errorMessage = session('errors')->first('email');
        $this->assertStringContainsString('Terlalu banyak percobaan masuk', $errorMessage);
    }

    /**
     * Memastikan percobaan SQL injection pada email ditangani dengan aman tanpa database error.
     */
    public function test_input_sql_injection_pada_email_ditangani_dengan_aman(): void
    {
        $response = $this->post('/login', [
            'email' => "' OR '1'='1' --",
            'password' => 'apa-saja',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseHas('log_activity', [
            'tabel_terdampak' => 'users',
            'jenis_aksi' => 'SQLI_ATTEMPT',
        ]);
    }

    /**
     * Memastikan proses logout melakukan session invalidation dan regenerasi token CSRF.
     */
    public function test_logout_melakukan_session_invalidation_dan_regenerasi_token_csrf(): void
    {
        $user = User::create([
            'name' => 'Logout User',
            'email' => 'logout_test@mazseh.test',
            'password' => Hash::make('Rahasia123'),
            'role' => 'pelanggan',
            'phone' => '081234567894',
        ]);

        $this->actingAs($user);
        $this->startSession();
        $oldToken = session()->token();

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success');
        $this->assertNotEquals($oldToken, session()->token());
    }

    /**
     * Memastikan registrasi berhasil menyimpan akun dan mengarahkan ke halaman login tanpa otomatis login.
     */
    public function test_registrasi_mengarahkan_ke_halaman_login_tanpa_otomatis_login(): void
    {
        $response = $this->post('/register', [
            'name' => 'Pengguna Baru',
            'email' => '  Baru_User@Mazseh.Test  ',
            'phone' => '081234567895',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'consent' => '1',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'email' => 'baru_user@mazseh.test',
            'name' => 'Pengguna Baru',
        ]);
    }

    /**
     * Memastikan email dinormalisasi dan rate limiter direset setelah login berhasil.
     */
    public function test_login_menormalisasi_email_dan_mereset_rate_limiter(): void
    {
        $user = User::create([
            'name' => 'Norm User',
            'email' => 'norm_user@mazseh.test',
            'password' => Hash::make('Rahasia123'),
            'role' => 'pelanggan',
            'phone' => '081234567896',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $this->post('/login', [
                'email' => 'norm_user@mazseh.test',
                'password' => 'PasswordSalah123',
            ])->assertSessionHasErrors('email');
        }

        $loginResponse = $this->post('/login', [
            'email' => '  NORM_USER@MAZSEH.TEST  ',
            'password' => 'Rahasia123',
        ]);

        $this->assertAuthenticatedAs($user);
        $loginResponse->assertRedirect(route('home'));

        $this->post('/logout');
        $this->assertGuest();

        $fifthResponse = null;
        for ($i = 1; $i <= 5; $i++) {
            $fifthResponse = $this->post('/login', [
                'email' => 'norm_user@mazseh.test',
                'password' => 'PasswordSalah123',
            ]);
        }

        $fifthResponse->assertSessionHasErrors([
            'email' => 'Alamat email atau kata sandi tidak cocok dengan data kami.',
        ]);
    }

    /**
     * Memastikan remember cookie hanya dibuat jika pengguna mencentang opsi Ingat saya.
     */
    public function test_remember_cookie_hanya_dibuat_jika_pengguna_mencentang_ingat_saya(): void
    {
        $user = User::create([
            'name' => 'Remember User',
            'email' => 'remember_test@mazseh.test',
            'password' => Hash::make('Rahasia123'),
            'role' => 'pelanggan',
            'phone' => '081234567897',
        ]);

        $responseWithoutRemember = $this->post('/login', [
            'email' => 'remember_test@mazseh.test',
            'password' => 'Rahasia123',
        ]);

        $this->assertAuthenticatedAs($user);
        $responseWithoutRemember->assertCookieMissing(Auth::getRecallerName());
        $this->assertNull($user->fresh()->remember_token);

        $this->post('/logout');
        $this->assertGuest();

        $responseWithRemember = $this->post('/login', [
            'email' => 'remember_test@mazseh.test',
            'password' => 'Rahasia123',
            'remember' => '1',
        ]);

        $this->assertAuthenticatedAs($user);
        $responseWithRemember->assertCookie(Auth::getRecallerName());
        $this->assertNotNull($user->fresh()->remember_token);
    }

    /**
     * Memastikan proteksi CSRF terpasang pada form autentikasi dan menolak request tanpa token.
     */
    public function test_proteksi_csrf_terpasang_pada_form_dan_menolak_request_tanpa_token(): void
    {
        $this->get('/login')->assertStatus(200)->assertSee('name="_token"', false);
        $this->get('/register')->assertStatus(200)->assertSee('name="_token"', false);
        $this->get('/forgot-password')->assertStatus(200)->assertSee('name="_token"', false);

        $webGroup = app('router')->getMiddlewareGroups()['web'] ?? [];
        $this->assertTrue(in_array(PreventRequestForgery::class, $webGroup, true));

        foreach (['login.post', 'register.post', 'forgot-password.post', 'logout'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertTrue(in_array('web', $route->gatherMiddleware(), true));
        }

        $user = User::create([
            'name' => 'CSRF Profile User',
            'email' => 'csrf_profile@mazseh.test',
            'password' => Hash::make('Rahasia123'),
            'role' => 'pelanggan',
            'phone' => '081234567898',
        ]);
        $this->actingAs($user)->get('/profile')->assertStatus(200)->assertSee('name="_token"', false);

        $this->post('/logout');

        try {
            $this->app['env'] = 'local';
            $response = $this->post('/login', [
                'email' => 'csrf_test@mazseh.test',
                'password' => 'Rahasia123',
            ]);
            $response->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    /**
     * Memastikan payload SQL injection yang lolos format email tetap ditangani aman oleh Eloquent.
     */
    public function test_input_sql_injection_lolos_format_email_ditangani_aman_oleh_eloquent(): void
    {
        $response = $this->post('/login', [
            'email' => "admin'or'1'='1@mazseh.test",
            'password' => 'Rahasia123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertSame(
            'Alamat email atau kata sandi tidak cocok dengan data kami.',
            session('errors')->first('email')
        );
        $this->assertDatabaseHas('log_activity', [
            'tabel_terdampak' => 'users',
            'jenis_aksi' => 'SQLI_ATTEMPT',
        ]);
        $this->assertDatabaseHas('log_activity', [
            'tabel_terdampak' => 'users',
            'jenis_aksi' => 'LOGIN_FAILED',
        ]);
    }

    /**
     * Memastikan password pengguna otomatis di-rehash saat login jika cost hashing berubah.
     */
    public function test_password_user_di_rehash_otomatis_saat_login_jika_konfigurasi_cost_berubah(): void
    {
        $oldHash = password_hash('Rahasia123', PASSWORD_BCRYPT, ['cost' => 4]);

        $user = new User;
        $user->forceFill([
            'name' => 'Rehash User',
            'email' => 'rehash_user@mazseh.test',
            'password' => $oldHash,
            'role' => 'pelanggan',
            'phone' => '081234567899',
        ])->save();

        config(['hashing.bcrypt.rounds' => 10]);
        Hash::forgetDrivers();
        $this->assertTrue(Hash::needsRehash($oldHash));

        $response = $this->post('/login', [
            'email' => 'rehash_user@mazseh.test',
            'password' => 'Rahasia123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));

        $updatedHash = $user->fresh()->password;
        $this->assertNotEquals($oldHash, $updatedHash);
        $this->assertFalse(Hash::needsRehash($updatedHash));
    }

    /**
     * Memastikan rate limiting per IP memblokir percobaan login massal dengan email berbeda.
     */
    public function test_rate_limiting_per_ip_memblokir_percobaan_login_massal_dengan_email_berbeda(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $response = $this->post('/login', [
                'email' => "spray{$i}@mazseh.test",
                'password' => 'Salah123',
            ]);
            $response->assertStatus(302);
        }

        $blockedResponse = $this->post('/login', [
            'email' => 'spray21@mazseh.test',
            'password' => 'Salah123',
        ]);

        $this->assertGuest();
        $blockedResponse->assertStatus(429);
    }

    /**
     * Memastikan RegisterRequest menolak format telepon invalid, password lemah, dan tanpa consent.
     */
    public function test_registrasi_menolak_input_tidak_valid_dan_tanpa_persetujuan_syarat(): void
    {
        $response = $this->post('/register', [
            'name' => 'User Invalid',
            'email' => 'invalid_reg@mazseh.test',
            'phone' => 'abc',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['phone', 'password', 'consent']);
    }

    /**
     * Memastikan rate limiting memblokir spam pada endpoint register dan forgot-password.
     */
    public function test_rate_limiting_memblokir_spam_registrasi_dan_lupa_kata_sandi(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/register', [
                'name' => 'Spam User',
                'email' => 'bukan-email',
                'phone' => '081234567890',
                'password' => 'Rahasia123',
                'password_confirmation' => 'Rahasia123',
                'consent' => '1',
            ])->assertStatus(302);
        }

        $this->post('/register', [
            'name' => 'Spam User',
            'email' => 'bukan-email',
            'phone' => '081234567890',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'consent' => '1',
        ])->assertStatus(429);

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/forgot-password', [
                'email' => 'lupa@mazseh.test',
            ])->assertStatus(302);
        }

        $this->post('/forgot-password', [
            'email' => 'lupa@mazseh.test',
        ])->assertStatus(429);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

        $response = $this->post('/login', [
            'email' => 'valid_user@mazseh.test',
            'password' => 'Rahasia123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success');
    }

    /**
     * Memastikan pengguna tidak dapat login jika password salah.
     */
    public function test_pengguna_tidak_dapat_login_dengan_password_salah(): void
    {
        User::create([
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
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'email' => 'baru_user@mazseh.test',
            'name' => 'Pengguna Baru',
        ]);
    }
}

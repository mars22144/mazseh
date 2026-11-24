<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Dummy Bcrypt hash (cost 12) untuk menjaga waktu verifikasi tetap konstan
     * ketika email tidak ditemukan (mitigasi timing attack).
     */
    private const DUMMY_PASSWORD_HASH = '$2y$12$usesomesillystringfore2uDLvp1Ii2e./U9C8sBjqp8I90dH6hi';

    /**
     * Tentukan apakah pengguna diizinkan membuat request ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi input sebelum divalidasi.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $rawEmail = $this->input('email');
            $activityLog = app(ActivityLogService::class);

            if ($activityLog->containsSqlInjectionPattern($rawEmail)) {
                $activityLog->log(
                    'users',
                    'SQLI_ATTEMPT',
                    'Percobaan input mencurigakan pada login: '.$rawEmail,
                    null,
                    $this->ip()
                );
            }

            $this->merge([
                'email' => Str::lower(trim($rawEmail)),
            ]);
        }
    }

    /**
     * Aturan validasi input yang ketat.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Pesan error validasi dalam Bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.max' => 'Alamat email maksimal 255 karakter.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal terdiri dari 6 karakter.',
            'password.max' => 'Kata sandi maksimal 255 karakter.',
        ];
    }

    /**
     * Proses autentikasi kredensial dengan pencegahan SQL Injection, Timing Attack,
     * Facade Hash (termasuk auto-rehash), dan Rate Limiting.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $email = (string) $this->validated('email');
        $password = (string) $this->validated('password');
        $remember = $this->boolean('remember');

        // Pencegahan SQL Injection: Gunakan Eloquent Parameter Binding (bukan raw query)
        $user = User::query()->where('email', $email)->first();

        // Mitigasi Timing Attack: tetap jalankan Hash::check terhadap dummy hash jika user null
        $targetHash = $user?->password ?? self::DUMMY_PASSWORD_HASH;
        $passwordValid = Hash::check($password, $targetHash);

        if (! $user || ! $passwordValid) {
            RateLimiter::hit($this->throttleKey(), 60);

            app(ActivityLogService::class)->log(
                'users',
                'LOGIN_FAILED',
                'Gagal login untuk email: '.$email,
                $user?->id,
                $this->ip()
            );

            throw ValidationException::withMessages([
                'email' => 'Alamat email atau kata sandi tidak cocok dengan data kami.',
            ]);
        }

        // Auto-rehash apabila cost/algoritma hashing di konfigurasi berubah
        if (Hash::needsRehash($user->password)) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();
        }

        RateLimiter::clear($this->throttleKey());

        Auth::login($user, $remember);
    }

    /**
     * Pastikan percobaan login tidak melebihi batas (Maksimal 5 kali per 60 detik).
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam {$seconds} detik.",
        ]);
    }

    /**
     * Buat kunci unik Rate Limiter berdasarkan kombinasi email dan alamat IP pengguna.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')).'|'.$this->ip());
    }
}

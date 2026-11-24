<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Tampilkan form login.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Proses autentikasi login pengguna.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Cegah Session Fixation dengan meregenerasi ID sesi baru
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $defaultDestination = $user->canAccessKasirPanel() ? route('kasir.dashboard') : route('home');

        return redirect()->intended($defaultDestination)
            ->with('success', 'Selamat datang kembali, '.$user->name.'!');
    }

    /**
     * Tampilkan form registrasi pengguna baru.
     */
    public function showRegister(): View
    {
        return view('auth.register');
    }

    /**
     * Proses registrasi pengguna baru.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => 'pelanggan',
        ]);

        return redirect()->route('login')
            ->with('success', 'Akun berhasil dibuat! Silakan masuk dengan email dan kata sandi Anda.');
    }

    /**
     * Tampilkan form lupa kata sandi.
     */
    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Simulasi kirim tautan reset kata sandi.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        return back()->with('status', 'Tautan pemulihan kata sandi telah dikirim ke alamat email Anda (simulasi).');
    }

    /**
     * Proses keluar akun (logout) dengan Session Invalidation penuh.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        // Hapus seluruh data sesi dan batalkan masa berlaku session ID lama
        $request->session()->invalidate();

        // Buat ulang CSRF token baru agar token lama tidak bisa dipakai ulang
        $request->session()->regenerateToken();

        return redirect()->route('home')
            ->with('success', 'Anda telah berhasil keluar akun.');
    }
}

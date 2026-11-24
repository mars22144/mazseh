<?php

namespace App\Http\Controllers;

use App\Models\LogActivity;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffSettingsController extends Controller
{
    /**
     * Halaman pengaturan akun staf: profil, kata sandi, sesi aktif, dan jejak aktivitas.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('staff.settings', [
            'user' => $user,
            'otherSessionCount' => $this->otherSessionsQuery($request)?->count(),
            'activities' => LogActivity::where('user_id', $user->id)->orderByDesc('id_log')->limit(10)->get(),
        ]);
    }

    /**
     * Perbarui nama, email, dan nomor telepon staf.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($validated);
        LogActivity::record('users', 'UPDATE', "Memperbarui profil akun sendiri ({$user->email}).");

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    /**
     * Ganti kata sandi: wajib kata sandi lama, lalu sesi lain otomatis dikeluarkan.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->mixedCase()->numbers()],
        ], [
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
        ]);

        $request->user()->update(['password' => Hash::make($validated['password'])]);
        $request->session()->regenerate();
        $this->otherSessionsQuery($request)?->delete();
        LogActivity::record('users', 'UPDATE', 'Mengganti kata sandi akun sendiri dan mengeluarkan sesi lain.');

        return back()->with('success', 'Kata sandi diganti. Sesi di perangkat lain telah dikeluarkan.');
    }

    /**
     * Keluarkan semua sesi akun ini di perangkat lain.
     */
    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $request->validateWithBag('sessions', [
            'current_password_sessions' => ['required', 'current_password'],
        ], [
            'current_password_sessions.current_password' => 'Kata sandi tidak sesuai.',
        ]);

        $removed = $this->otherSessionsQuery($request)?->delete() ?? 0;
        LogActivity::record('sessions', 'DELETE', "Mengeluarkan {$removed} sesi lain dari akun sendiri.");

        return back()->with('success', "{$removed} sesi di perangkat lain telah dikeluarkan.");
    }

    /**
     * Query sesi lain milik pengguna (hanya tersedia bila sesi disimpan di database).
     */
    private function otherSessionsQuery(Request $request): ?Builder
    {
        if (config('session.driver') !== 'database') {
            return null;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId());
    }
}

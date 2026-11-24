<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentPelanggan;
use App\Models\LogActivity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Peran yang dapat diberikan ke pengguna.
     *
     * @var array<string, string>
     */
    public const ROLES = [
        'pelanggan' => 'Pelanggan',
        'kasir' => 'Kasir',
        'admin' => 'Admin',
    ];

    /**
     * Daftar pengguna dengan filter peran, pencarian, dan antrean verifikasi dokumen.
     */
    public function index(Request $request): View
    {
        $role = array_key_exists((string) $request->query('peran'), self::ROLES) ? (string) $request->query('peran') : null;
        $onlyPendingDocuments = $request->boolean('dokumen');
        $search = trim((string) $request->query('q', ''));

        $users = User::with('dokumenPelanggan')
            ->withCount('transaksi')
            ->when($role, fn ($query, string $role) => $query->where('role', $role))
            ->when($onlyPendingDocuments, fn ($query) => $query->whereHas('dokumenPelanggan', fn ($doc) => $doc->where('status_verifikasi', 'menunggu_validasi')))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'kasir' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'role' => $role,
            'search' => $search,
            'onlyPendingDocuments' => $onlyPendingDocuments,
            'roles' => self::ROLES,
            'pendingDocumentCount' => DocumentPelanggan::where('status_verifikasi', 'menunggu_validasi')->count(),
        ]);
    }

    /**
     * Ubah peran pengguna (admin tidak dapat mengubah perannya sendiri).
     */
    public function updateRole(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak dapat mengubah peran akun Anda sendiri.');
        }

        $validated = $request->validate(['role' => ['required', Rule::in(array_keys(self::ROLES))]]);
        $previousRole = $user->role;
        $user->forceFill(['role' => $validated['role']])->save();
        LogActivity::record('users', 'UPDATE', "Mengubah peran {$user->email} dari {$previousRole} menjadi {$validated['role']}.");

        return back()->with('success', "Peran {$user->name} kini ".self::ROLES[$validated['role']].'.');
    }

    /**
     * Setujui atau tolak dokumen KTP & SIM pelanggan.
     */
    public function verifyDocument(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['disetujui', 'ditolak'])]]);
        $document = DocumentPelanggan::where('user_id', $id)->firstOrFail();
        $document->update(['status_verifikasi' => $validated['status']]);
        LogActivity::record('document_pelanggan', 'UPDATE', "Dokumen KTP/SIM {$document->user->email} {$validated['status']}.");

        return back()->with('success', 'Dokumen '.$document->user->name.' '.($validated['status'] === 'disetujui' ? 'disetujui.' : 'ditolak.'));
    }
}

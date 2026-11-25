<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\VerifyDokumenRequest;
use App\Models\DocumentPelanggan;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DokumenController extends Controller
{
    public function __construct(public ActivityLogService $activityLog) {}

    /**
     * Tampilkan daftar dokumen identitas KTP & SIM pelanggan untuk verifikasi.
     */
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));

        $query = DocumentPelanggan::with('user')
            ->orderByRaw("CASE WHEN status_verifikasi = 'menunggu_validasi' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at');

        if (in_array($status, ['menunggu_validasi', 'disetujui', 'ditolak', 'belum_upload'], true)) {
            $query->where('status_verifikasi', $status);
        }

        if ($search !== '') {
            $query->whereHas('user', function (Builder $user) use ($search): void {
                $user->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('sim_number', 'like', "%{$search}%");
            });
        }

        $dokumenList = $query->paginate(15)->withQueryString();

        return view('kasir.dokumen.index', compact('dokumenList'));
    }

    /**
     * Perbarui status verifikasi dokumen KTP & SIM pelanggan dan catat log aktivitas.
     */
    public function verify(VerifyDokumenRequest $request, int $id): RedirectResponse
    {
        $dokumen = DocumentPelanggan::with('user')->findOrFail($id);
        $validated = $request->validated();

        $dokumen->update([
            'status_verifikasi' => $validated['status_verifikasi'],
            'catatan_verifikasi' => $validated['catatan_verifikasi'] ?? null,
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $this->activityLog->log(
            'document_pelanggan',
            'UPDATE',
            "Verifikasi dokumen pelanggan {$dokumen->user->name}: {$dokumen->status_verifikasi}",
            $actor->id
        );

        return back()->with('success', 'Status verifikasi dokumen '.$dokumen->user->name.' berhasil diperbarui.');
    }
}

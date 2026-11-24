<?php

namespace App\Http\Controllers;

use App\Models\DocumentPelanggan;
use App\Models\Pembayaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SecureFileController extends Controller
{
    /**
     * Tampilkan foto KTP/SIM hanya untuk pemiliknya atau staf (kasir/admin).
     */
    public function document(Request $request, int $userId, string $type): Response
    {
        $this->authorizeOwnerOrStaff($request->user(), $userId);

        $document = DocumentPelanggan::where('user_id', $userId)->firstOrFail();

        return $this->streamPrivateFile($type === 'ktp' ? $document->file_ktp : $document->file_sim);
    }

    /**
     * Tampilkan bukti pembayaran hanya untuk pemilik pesanan atau staf.
     */
    public function paymentProof(Request $request, int $paymentId): Response
    {
        $payment = Pembayaran::with('transaksi')->findOrFail($paymentId);

        $this->authorizeOwnerOrStaff($request->user(), (int) $payment->transaksi?->user_id);

        return $this->streamPrivateFile($payment->bukti_bayar);
    }

    /**
     * Tolak akses bila pengguna bukan pemilik data dan bukan staf.
     */
    private function authorizeOwnerOrStaff(User $user, int $ownerId): void
    {
        abort_unless($user->id === $ownerId || $user->canAccessKasirPanel(), 403);
    }

    /**
     * Kirim berkas dari disk privat tanpa cache. Path lama (/storage/...) dibaca dari disk publik.
     */
    private function streamPrivateFile(?string $path): Response
    {
        abort_if(blank($path), 404);

        [$disk, $relativePath] = str_starts_with($path, '/storage/')
            ? ['public', substr($path, strlen('/storage/'))]
            : ['local', $path];

        abort_unless(Storage::disk($disk)->exists($relativePath), 404);

        return Storage::disk($disk)->response($relativePath, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

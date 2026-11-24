<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAccount
{
    /**
     * Alur pemesanan hanya untuk akun pelanggan; akun staf dikembalikan ke panelnya.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->canAccessKasirPanel()) {
            abort_if($request->expectsJson(), 403, 'Akun staf tidak dapat melakukan pemesanan.');

            return redirect()->route($user->hasRole('admin') ? 'admin.dashboard' : 'kasir.dashboard')
                ->with('error', 'Akun staf tidak dapat memesan atau mengelola pesanan pelanggan. Gunakan akun pelanggan untuk menyewa.');
        }

        return $next($request);
    }
}

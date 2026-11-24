<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Batasi akses route hanya untuk pengguna dengan salah satu peran yang diizinkan.
     *
     * Contoh pemakaian: `->middleware('role:kasir,admin')`.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            abort(403, 'Halaman ini hanya dapat diakses oleh staf yang berwenang.');
        }

        return $next($request);
    }
}

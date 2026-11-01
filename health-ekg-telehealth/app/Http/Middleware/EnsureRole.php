<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Batasi akses berdasarkan role pengguna.
     * Penggunaan: ->middleware('role:admin') atau ->middleware('role:dokter,pasien').
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Pengguna belum terautentikasi.');
        }

        if (! in_array($user->role, $roles, true)) {
            \App\Models\AuditLog::record('akses_ditolak', 'route', null, [
                'path' => $request->path(),
                'role' => $user->role,
                'butuh' => implode(',', $roles),
            ]);
            abort(403, 'Anda tidak memiliki hak akses ke halaman ini.');
        }

        return $next($request);
    }
}

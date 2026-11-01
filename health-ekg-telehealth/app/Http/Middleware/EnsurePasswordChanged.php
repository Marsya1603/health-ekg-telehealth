<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Wajibkan penggantian password awal bila kebijakan aplikasi mengaktifkannya.
     * Hanya halaman ubah password dan logout yang diizinkan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password || ! config('healthekg.force_password_change', true)) {
            return $next($request);
        }

        $allowed = ['password.change', 'password.update', 'logout'];

        if (in_array($request->route()?->getName(), $allowed, true)) {
            return $next($request);
        }

        return redirect()
            ->route('password.change')
            ->with('warning', 'Anda wajib mengganti password sebelum menggunakan aplikasi.');
    }
}

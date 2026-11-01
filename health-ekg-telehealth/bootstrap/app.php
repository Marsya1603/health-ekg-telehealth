<?php

use App\Http\Middleware\EnsureActive;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');

        // PENTING: tanpa ini, middleware "guest" bawaan Laravel me-redirect
        // user yang sudah login kembali ke "/" (fallback defaultRedirectUri),
        // sementara "/" me-redirect ke login => redirect loop tak berujung.
        // Callback HARUS mengembalikan URL (route()), bukan nama rute.
        $middleware->redirectUsersTo(function (Illuminate\Http\Request $request) {
            $user = $request->user();

            return $user ? route($user->homeRoute()) : '/login';
        });

        $middleware->alias([
            'role' => EnsureRole::class,
            'active' => EnsureActive::class,
            'password' => EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

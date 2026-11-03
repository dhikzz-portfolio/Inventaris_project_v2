<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureOperationalInventoryAccess;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureProfileCompleted;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'profile.completed' => EnsureProfileCompleted::class,
            'inventory.access' => EnsureOperationalInventoryAccess::class,
            'password.changed' => EnsurePasswordChanged::class,
        ]);

        // Semua sesi memverifikasi hash password terbaru pada setiap request.
        // Setelah password berubah, sesi lain otomatis diputus.
        $middleware->web(append: [
            AuthenticateSession::class,
            EnsureAccountIsActive::class,
        ]);

        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

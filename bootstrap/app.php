<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\EnsureSsoAuthenticated;
use App\Http\Middleware\ResolveOpdContext;
use App\Http\Middleware\SessionHealthCheck;
use App\Http\Middleware\DetectLoopAttempt;

$spatieRole = class_exists('\\Spatie\\Permission\\Middlewares\\RoleMiddleware')
    ? '\\Spatie\\Permission\\Middlewares\\RoleMiddleware'
    : '\\Spatie\\Permission\\Middleware\\RoleMiddleware';

$spatiePerm = class_exists('\\Spatie\\Permission\\Middlewares\\PermissionMiddleware')
    ? '\\Spatie\\Permission\\Middlewares\\PermissionMiddleware'
    : '\\Spatie\\Permission\\Middleware\\PermissionMiddleware';

$spatieRoleOrPerm = class_exists('\\Spatie\\Permission\\Middlewares\\RoleOrPermissionMiddleware')
    ? '\\Spatie\\Permission\\Middlewares\\RoleOrPermissionMiddleware'
    : '\\Spatie\\Permission\\Middleware\\RoleOrPermissionMiddleware';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) use ($spatieRole, $spatiePerm, $spatieRoleOrPerm): void {

        $middleware->alias([
            // Spatie (route kamu pakai "role:...")
            'role'               => $spatieRole,
            'permission'         => $spatiePerm,
            'role_or_permission' => $spatieRoleOrPerm,

            // SSO gate
            'sso.auth'           => EnsureSsoAuthenticated::class,

            // OPD context
            'resolve.opd'        => ResolveOpdContext::class,
            'api.publik.key'     => \App\Http\Middleware\ValidasiApiKeyPublik::class,

            // Prevention (optional, use on specific routes only)
            'session.health'     => SessionHealthCheck::class,
            'detect.loop'        => DetectLoopAttempt::class,
        ]);

        // Build web middleware list ONCE (jangan panggil web() berkali-kali)
        $webAppend = [
            ResolveOpdContext::class,
        ];

        // Optional middlewares (kalau file/class memang ada)
        if (class_exists(\App\Http\Middleware\LogPageVisit::class)) {
            $webAppend[] = \App\Http\Middleware\LogPageVisit::class;
        }
        if (class_exists(\App\Http\Middleware\LogDataMutation::class)) {
            $webAppend[] = \App\Http\Middleware\LogDataMutation::class;
        }

        $middleware->web(append: $webAppend);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();

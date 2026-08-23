<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsureAccountCanAccess;
use App\Http\Middleware\EnsureStaffTwoFactorEnabled;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\TrackAuthenticatedSession;
use App\Http\Middleware\TrustConfiguredProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignCorrelationId::class);
        $middleware->replace(TrustProxies::class, TrustConfiguredProxies::class);
        $middleware->web(append: [
            EnsureAccountCanAccess::class,
            TrackAuthenticatedSession::class,
        ]);
        $middleware->alias([
            'staff.2fa' => EnsureStaffTwoFactorEnabled::class,
            'permission' => RequirePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

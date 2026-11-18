<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\StaffMiddleware;
use App\Http\Middleware\SyncMovieSchedule;
use App\Http\Middleware\TrustCloudProxies;
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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->replace(TrustProxies::class, TrustCloudProxies::class);
        $middleware->web(prepend: [SyncMovieSchedule::class], append: [EnsureUserIsActive::class]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'staff' => StaffMiddleware::class,
        ]);

        $middleware->preventRequestForgery(except: [
            'payments/momo/ipn',
            'payments/sepay/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

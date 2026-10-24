<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\StaffMiddleware;
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
        $middleware->web(append: [EnsureUserIsActive::class]);

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

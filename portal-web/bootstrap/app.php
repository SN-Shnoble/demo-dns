<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'node.token' => \App\Http\Middleware\AuthenticateNodeToken::class,
            'node.hmac' => \App\Http\Middleware\VerifyRequestSignature::class,
            'shared.token' => \App\Http\Middleware\RequireSharedToken::class,
            'user.only' => \App\Http\Middleware\UserOnly::class,
            'admin.only' => \App\Http\Middleware\AdminOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

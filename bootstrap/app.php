<?php

use App\Http\Middleware\AdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api('auth:sanctum');
        // The API sits behind a proxy on the same machine (and the website's Nuxt server calls it from
        // there too), so every request came from 127.0.0.1. Trust only local proxies: the real client
        // address is then read from X-Forwarded-For, and someone on the internet can't fake it.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })->create();

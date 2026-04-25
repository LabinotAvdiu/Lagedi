<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apache on the same VPS reverse-proxies social-crawler hits from
        // www.termini-im.com → api.termini-im.com/share/company/{id}. Without
        // trusting the proxy, every bot would appear to originate from the
        // loopback IP and the per-IP throttle on share.company would collapse
        // into a single global bucket.
        //
        // The trust list is intentionally narrow: only the VPS itself can
        // forge X-Forwarded-For, since the API is publicly reachable.
        $proxies = array_values(array_filter([
            '127.0.0.1',
            '::1',
            env('TRUSTED_PROXY_IP'), // VPS public IP — set in .env
        ]));

        $middleware->trustProxies(
            at: $proxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })->create();

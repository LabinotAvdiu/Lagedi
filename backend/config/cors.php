<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    | Configured for the Flutter mobile app (no browser origin) and the
    | local Vite dev frontend. In production, restrict 'allowed_origins'
    | to your actual domain(s) and set 'supports_credentials' => false
    | (mobile apps do not use cookies).
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        // Local Vite frontend
        'http://localhost:5173',
        // Flutter web (debug)
        'http://localhost:5555',
        // Production domains
        'https://termini-im.com',
        'https://app.termini-im.com',
    ],

    // Allow all origins for mobile app (Flutter does not send an Origin header
    // on non-browser requests, so this does not weaken browser security).
    // In dev we also accept any localhost port so `flutter run -d chrome`
    // (which picks a random port each run) just works.
    'allowed_origins_patterns' => [
        '#^http://localhost:\d+$#',
        '#^http://127\.0\.0\.1:\d+$#',
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
        'X-App-Version',
    ],

    'exposed_headers' => [],

    'max_age' => 86400, // 24 hours preflight cache

    'supports_credentials' => false, // Mobile app uses Bearer tokens, not cookies

];

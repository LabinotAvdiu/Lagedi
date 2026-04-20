<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Google Sign In — comma-separated list of OAuth client IDs that are
    // authorised to issue tokens for this backend (web + android + iOS).
    // Leave empty to skip the aud check (dev only — enable in production).
    'google' => [
        'allowed_client_ids' => env('GOOGLE_ALLOWED_CLIENT_IDS'),
    ],

    // Apple Sign In — client_id is the Bundle ID (iOS app) or Service ID (web).
    // Leave null to skip the aud check (dev only — enable in production).
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
    ],

];

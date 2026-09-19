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

    'giphy' => [
        'key' => env('GIPHY_API_KEY'),
    ],

    // WebRTC ICE. STUN alone only works when both peers are behind
    // friendly NATs; a TURN relay is what makes calls connect reliably.
    'stun' => [
        'urls' => env('STUN_URLS', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302'),
    ],

    'turn' => [
        // Comma-separated, e.g. "turn:turn.example.com:3478?transport=udp,turns:turn.example.com:5349"
        'urls'       => env('TURN_URLS'),
        // coturn `static-auth-secret`: the server mints short-lived per-user
        // credentials, so nothing long-lived ever reaches the browser.
        'secret'     => env('TURN_SECRET'),
        'ttl'        => (int) env('TURN_CREDENTIAL_TTL', 3600),
        // Fallback for providers that only offer a fixed username/password.
        'username'   => env('TURN_USERNAME'),
        'credential' => env('TURN_CREDENTIAL'),
    ],

];

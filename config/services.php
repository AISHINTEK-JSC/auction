<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // Cloudflare Turnstile: the captcha on registration and on posting a listing.
    // Leave both empty to turn the captcha off.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'auction_engine' => [
        'url' => env('AUCTION_ENGINE_URL', 'http://localhost:8801/api/v1'),
        'token' => env('AUCTION_ENGINE_TOKEN'),
        'push_key' => env('AUCTION_ENGINE_PUSH_KEY'),
        'timeout' => (int) env('AUCTION_ENGINE_TIMEOUT', 5),
        // 1 = proxy (second price + step), 2 = highest bid is the displayed price
        'calculate_type' => (int) env('AUCTION_ENGINE_CALCULATE_TYPE', 2),
    ],

];

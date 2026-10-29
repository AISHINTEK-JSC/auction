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

    // Amazon SES, used when MAIL_MAILER=ses (or when an admin picks it). With
    // no key the SDK falls back to the server's IAM role.
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_SES_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
    ],

    // Text messages: the codes that confirm a member's phone number.
    // "log" writes them to the log instead of sending; "sns" sends them through Amazon SNS.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    'sns' => [
        'key' => env('AWS_SNS_KEY', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('AWS_SNS_SECRET', env('AWS_SECRET_ACCESS_KEY')),
        'region' => env('AWS_SNS_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        // Brand name registered with Vietnamese carriers. Empty: the carrier's default sender.
        'sender_id' => env('AWS_SNS_SENDER_ID'),
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

    // "Tiếp tục với Google" on the sign-in and sign-up pages. Leave the id
    // empty to hide the button. The callback URL registered with Google is
    // APP_URL/auth/google/callback unless GOOGLE_REDIRECT_URI says otherwise.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
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

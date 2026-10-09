<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fixed-price listings
    |--------------------------------------------------------------------------
    |
    | A fixed-price listing stays up for this many days, then expires on its
    | own. Its seller can renew it or put it up again from "Tin đăng của tôi".
    |
    | These are the defaults: an admin can override every value in this file
    | from the admin panel (Cài đặt), see App\Support\MarketplaceSettings.
    |
    */

    'fixed_listing_days' => (int) env('FIXED_LISTING_DAYS', 30),

    // The seller is reminded this many days before the listing expires (0 = never).
    'expiry_reminder_days' => (int) env('EXPIRY_REMINDER_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Winners who back out
    |--------------------------------------------------------------------------
    |
    | Each time the winner of an auction gives up the result it is recorded.
    | Within the window below, the Nth time earns a ban from bidding for the
    | given number of days; the highest configured step applies beyond it.
    | The first time is only a warning.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    |
    | The address shown on the terms and privacy pages for questions and
    | requests about personal data. An admin can replace it in the admin
    | panel (Cài đặt > Email liên hệ); this is the value used until then.
    |
    */

    // "?:" and not a default: an empty CONTACT_EMAIL= line in .env must fall back too.
    'contact_email' => env('CONTACT_EMAIL') ?: env('MAIL_FROM_ADDRESS', 'hello@example.com'),

    'forfeit_window_days' => (int) env('FORFEIT_WINDOW_DAYS', 90),

    'forfeit_bans' => [
        2 => 7,
        3 => 30,
    ],

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fixed-price listings
    |--------------------------------------------------------------------------
    |
    | A fixed-price listing stays up for this many days, then expires on its
    | own. Its seller can put it up again from "Tin đăng của tôi".
    |
    */

    'fixed_listing_days' => (int) env('FIXED_LISTING_DAYS', 30),

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

    'forfeit_window_days' => (int) env('FORFEIT_WINDOW_DAYS', 90),

    'forfeit_bans' => [
        2 => 7,
        3 => 30,
    ],

];

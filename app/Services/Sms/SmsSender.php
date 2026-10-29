<?php

namespace App\Services\Sms;

/**
 * Sends one text message. Which implementation is used follows
 * config('services.sms.driver'), see AppServiceProvider.
 */
interface SmsSender
{
    /**
     * @param  string  $phone  in international form, +84912345678
     */
    public function send(string $phone, string $message): void;
}

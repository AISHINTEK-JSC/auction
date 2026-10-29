<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Writes the message to the log instead of sending it: local development and
 * any install without an SMS account. The code can be read in storage/logs.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS to {$phone}: {$message}");
    }
}

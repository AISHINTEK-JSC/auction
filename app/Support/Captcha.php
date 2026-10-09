<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cloudflare Turnstile, the "are you a robot" check on registration and on
 * posting a listing. It is off until both keys are set (TURNSTILE_SITE_KEY and
 * TURNSTILE_SECRET_KEY), so a fresh checkout works without an account.
 */
class Captcha
{
    /** The form field Turnstile's widget fills in. */
    public const FIELD = 'cf-turnstile-response';

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function enabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    /**
     * The public key the browser widget needs, or null when the check is off.
     */
    public static function siteKey(): ?string
    {
        return self::enabled() ? (string) config('services.turnstile.site_key') : null;
    }

    /**
     * Ask Cloudflare whether the token the browser sent is genuine. A token
     * can be checked once only.
     */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            return Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]))
                ->json('success') === true;
        } catch (Throwable $e) {
            // Cloudflare unreachable: refuse rather than let everything through.
            report($e);

            return false;
        }
    }
}

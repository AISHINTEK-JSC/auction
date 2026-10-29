<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Connection settings an admin can change in the admin panel (Cài đặt): how
 * the site's email goes out (an SMTP server or Amazon SES), how text messages
 * go out (the log or Amazon SNS), and the auction engine.
 *
 * The .env file stays the default. Whatever is saved here is laid over the
 * configuration at the start of every request and console command, so the
 * rest of the code keeps reading config('mail…'), config('services.ses…'),
 * config('services.sns…') and config('services.auction_engine…') as before.
 * A field left empty falls back to .env.
 *
 * Passwords and keys are encrypted with the application key before they are
 * stored, and are never sent back to the browser.
 */
class IntegrationSettings
{
    /** The mailer in use, the SMTP server and the sender. */
    public const SMTP = 'smtp';

    public const SES = 'ses';

    public const SMS = 'sms';

    public const ENGINE = 'auction_engine';

    /** Mailers an admin can pick. */
    public const MAILERS = ['smtp' => 'SMTP', 'ses' => 'Amazon SES'];

    /** SMS drivers an admin can pick. */
    public const SMS_DRIVERS = ['log' => 'Không gửi, chỉ ghi log', 'sns' => 'Amazon SNS'];

    /** Field of a group => the configuration key it overrides. */
    private const FIELDS = [
        self::SMTP => [
            'mailer' => 'mail.default',
            'host' => 'mail.mailers.smtp.host',
            'port' => 'mail.mailers.smtp.port',
            'scheme' => 'mail.mailers.smtp.scheme',
            'username' => 'mail.mailers.smtp.username',
            'password' => 'mail.mailers.smtp.password',
            'from_address' => 'mail.from.address',
            'from_name' => 'mail.from.name',
        ],
        self::SES => [
            'key' => 'services.ses.key',
            'secret' => 'services.ses.secret',
            'region' => 'services.ses.region',
        ],
        self::SMS => [
            'driver' => 'services.sms.driver',
            'key' => 'services.sns.key',
            'secret' => 'services.sns.secret',
            'region' => 'services.sns.region',
            'sender_id' => 'services.sns.sender_id',
        ],
        self::ENGINE => [
            'url' => 'services.auction_engine.url',
            'token' => 'services.auction_engine.token',
            'push_key' => 'services.auction_engine.push_key',
        ],
    ];

    private const SECRETS = [
        self::SMTP => ['password'],
        self::SES => ['secret'],
        self::SMS => ['secret'],
        self::ENGINE => ['token', 'push_key'],
    ];

    /** Container key of the configuration as .env gave it (see defaults()). */
    private const DEFAULTS = 'integration-settings.defaults';

    /**
     * Lay the saved settings over the configuration. Called while the
     * application boots; does nothing when the database cannot be read (a
     * fresh install that has not been migrated yet).
     */
    public static function apply(): void
    {
        config(self::defaults());

        try {
            $stored = array_map(self::stored(...), array_combine(array_keys(self::FIELDS), array_keys(self::FIELDS)));
        } catch (Throwable) {
            return;
        }

        foreach ($stored as $group => $values) {
            foreach ($values as $field => $value) {
                config([self::FIELDS[$group][$field] => $field === 'port' ? (int) $value : $value]);
            }
        }

        // A server named here without a mailer picked is meant to be used,
        // whatever MAIL_MAILER says (it may be "log").
        if (! isset($stored[self::SMTP]['mailer']) && isset($stored[self::SMTP]['host'])) {
            config(['mail.default' => 'smtp']);
        }

        // A mailer built before this ran would keep the old server or keys.
        if (app()->resolved('mail.manager')) {
            Mail::purge('smtp');
            Mail::purge('ses');
        }
    }

    /**
     * What an admin saved for a group, secrets decrypted. Only fields that
     * were filled in are present.
     *
     * @return array<string, string>
     */
    public static function stored(string $group): array
    {
        $stored = Setting::read($group);
        $values = [];

        foreach (array_keys(self::FIELDS[$group]) as $field) {
            $value = is_array($stored) ? ($stored[$field] ?? null) : null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            if (in_array($field, self::SECRETS[$group], true)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (DecryptException) {
                    // Encrypted with another APP_KEY: as good as not set.
                    continue;
                }
            }

            $values[$field] = $value;
        }

        return $values;
    }

    /**
     * Save a group. An empty plain field goes back to .env; an empty secret
     * keeps the one already saved, since the form never shows it.
     *
     * @param  array<string, mixed>  $input
     * @return list<string> the secrets that were replaced
     */
    public static function save(string $group, array $input): array
    {
        $current = self::stored($group);
        $values = [];
        $replaced = [];

        foreach (array_keys(self::FIELDS[$group]) as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            $secret = in_array($field, self::SECRETS[$group], true);

            if ($secret && $value === '') {
                $value = $current[$field] ?? '';
            } elseif ($secret && $value !== ($current[$field] ?? null)) {
                $replaced[] = $field;
            }

            if ($value !== '') {
                $values[$field] = $secret ? Crypt::encryptString($value) : $value;
            }
        }

        $values === [] ? Setting::forget($group) : Setting::write($group, $values);

        self::apply();

        return $replaced;
    }

    /**
     * Drop everything saved for a group: .env is in charge again.
     */
    public static function reset(string $group): void
    {
        Setting::forget($group);

        self::apply();
    }

    /**
     * Where the value in force comes from: 'admin' when it was saved here,
     * 'env' when .env supplies it, null when it is not set anywhere.
     */
    public static function source(string $group, string $field): ?string
    {
        if (isset(self::stored($group)[$field])) {
            return 'admin';
        }

        return filled(config(self::FIELDS[$group][$field])) ? 'env' : null;
    }

    /**
     * The configuration as .env gave it, remembered the first time this runs
     * so that a field emptied later in the same process goes back to it. Kept
     * in the container, not in a static: it belongs to this application instance.
     *
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        if (! app()->bound(self::DEFAULTS)) {
            $keys = array_merge(...array_map(array_values(...), array_values(self::FIELDS)));

            app()->instance(self::DEFAULTS, array_combine($keys, array_map(fn (string $key) => config($key), $keys)));
        }

        return app(self::DEFAULTS);
    }
}

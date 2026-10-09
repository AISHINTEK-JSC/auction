<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The marketplace rules an admin can change at runtime (admin panel > Cài đặt).
 * Anything not set there falls back to config/marketplace.php.
 */
class MarketplaceSettings
{
    public const KEY = 'marketplace';

    public const CONTACT_EMAIL = 'contact_email';

    /**
     * Every setting with its current value.
     *
     * @return array{fixed_listing_days: int, expiry_reminder_days: int, forfeit_window_days: int, forfeit_ban_second_days: int, forfeit_ban_repeat_days: int}
     */
    public static function all(): array
    {
        $stored = Setting::read(self::KEY);
        $stored = is_array($stored) ? $stored : [];
        $bans = (array) config('marketplace.forfeit_bans', []);

        return [
            'fixed_listing_days' => max(1, (int) ($stored['fixed_listing_days'] ?? config('marketplace.fixed_listing_days', 30))),
            'expiry_reminder_days' => max(0, (int) ($stored['expiry_reminder_days'] ?? config('marketplace.expiry_reminder_days', 3))),
            'forfeit_window_days' => max(1, (int) ($stored['forfeit_window_days'] ?? config('marketplace.forfeit_window_days', 90))),
            'forfeit_ban_second_days' => max(0, (int) ($stored['forfeit_ban_second_days'] ?? ($bans[2] ?? 7))),
            'forfeit_ban_repeat_days' => max(0, (int) ($stored['forfeit_ban_repeat_days'] ?? ($bans[3] ?? 30))),
        ];
    }

    /**
     * @param  array<string, int>  $values
     */
    public static function save(array $values): void
    {
        Setting::write(self::KEY, array_intersect_key($values, self::all()));
    }

    /**
     * The address shown on the terms and privacy pages. Until an admin sets
     * one, the address from the environment (CONTACT_EMAIL) is used.
     */
    public static function contactEmail(): string
    {
        $stored = Setting::read(self::CONTACT_EMAIL);

        return is_string($stored) && $stored !== '' ? $stored : (string) config('marketplace.contact_email');
    }

    /**
     * An empty address goes back to the one from the environment.
     */
    public static function saveContactEmail(?string $email): void
    {
        Setting::write(self::CONTACT_EMAIL, trim((string) $email));
    }

    /** How long a fixed-price listing stays up. */
    public static function fixedListingDays(): int
    {
        return self::all()['fixed_listing_days'];
    }

    /** How many days before the end the seller is reminded; 0 turns the reminder off. */
    public static function expiryReminderDays(): int
    {
        return self::all()['expiry_reminder_days'];
    }

    /** How far back forfeits count against a member. */
    public static function forfeitWindowDays(): int
    {
        return self::all()['forfeit_window_days'];
    }

    /**
     * Days of bidding ban for the Nth forfeit within the window. The first is
     * only a warning; a step set to 0 days is skipped.
     *
     * @return array<int, int>
     */
    public static function forfeitBans(): array
    {
        $settings = self::all();

        return array_filter([
            2 => $settings['forfeit_ban_second_days'],
            3 => $settings['forfeit_ban_repeat_days'],
        ]);
    }
}

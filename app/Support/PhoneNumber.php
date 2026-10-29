<?php

namespace App\Support;

/**
 * Vietnamese phone numbers, as members type them: 0912345678 or +84912345678.
 */
class PhoneNumber
{
    /** Validation rule for a number typed in a form. */
    public const RULE = 'regex:/^(0|\+84)\d{9,10}$/';

    /**
     * The international form SMS gateways expect: +84912345678.
     */
    public static function international(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';

        return str_starts_with($phone, '0') ? '+84'.substr($phone, 1) : $phone;
    }

    /**
     * Both ways the same number may have been saved, to find it whichever was used.
     *
     * @return list<string>
     */
    public static function variants(string $phone): array
    {
        $international = self::international($phone);

        return array_values(array_unique([$international, '0'.substr($international, 3)]));
    }
}

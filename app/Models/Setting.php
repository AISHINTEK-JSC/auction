<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value store for settings an admin edits at runtime.
 *
 * @property string $key
 * @property mixed $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const PASSWORD_POLICY = 'password_policy';

    /** When true, new listings wait for a moderator before they go live. */
    public const LISTING_APPROVAL = 'listing_approval';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("settings.{$key}", fn () => self::query()->find($key)?->value) ?? $default;
    }

    public static function write(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("settings.{$key}");
    }

    /**
     * @return array{min_length: int, mixed_case: bool, numbers: bool, symbols: bool}
     */
    public static function passwordPolicy(): array
    {
        $stored = self::read(self::PASSWORD_POLICY);
        $stored = is_array($stored) ? $stored : [];

        return [
            'min_length' => max(6, (int) ($stored['min_length'] ?? 8)),
            'mixed_case' => (bool) ($stored['mixed_case'] ?? false),
            'numbers' => (bool) ($stored['numbers'] ?? false),
            'symbols' => (bool) ($stored['symbols'] ?? false),
        ];
    }
}

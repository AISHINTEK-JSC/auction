<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $name
 * @property int $priority
 */
#[Fillable(['name', 'priority'])]
class City extends Model
{
    public const OPTIONS_CACHE_KEY = 'cities.options';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::OPTIONS_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::OPTIONS_CACHE_KEY));
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public static function options(): array
    {
        return Cache::remember(self::OPTIONS_CACHE_KEY, now()->addMinutes(10), fn () => array_values(self::query()
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (self $city) => ['id' => $city->id, 'name' => $city->name])
            ->all()));
    }
}

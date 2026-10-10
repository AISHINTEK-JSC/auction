<?php

namespace App\Support;

use App\Models\Listing;

/**
 * The brand a seller may name on a listing ("Apple", "Seiko"). Free text, so
 * it is tidied here to keep one brand from being spelt three ways.
 */
class Brand
{
    public const MAX_LENGTH = 60;

    /**
     * Trimmed, single-spaced, and spelt the way it already is on other
     * listings ("apple" becomes "Apple" once someone wrote it that way).
     * Null when nothing was entered.
     */
    public static function normalize(?string $brand): ?string
    {
        $brand = trim((string) preg_replace('/\s+/u', ' ', (string) $brand));

        if ($brand === '') {
            return null;
        }

        return Listing::query()
            ->whereRaw('lower(brand) = ?', [mb_strtolower($brand)])
            ->orderBy('id')
            ->value('brand') ?? $brand;
    }

    /**
     * Brands already in use on listings anyone can see, most used first:
     * suggestions for the listing form.
     *
     * @return list<string>
     */
    public static function suggestions(int $limit = 100): array
    {
        return array_values(Listing::query()
            ->published()
            ->whereNotNull('brand')
            ->selectRaw('brand, count(*) as uses')
            ->groupBy('brand')
            ->orderByDesc('uses')
            ->orderBy('brand')
            ->limit($limit)
            ->pluck('brand')
            ->all());
    }
}

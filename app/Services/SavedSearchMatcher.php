<?php

namespace App\Services;

use App\Enums\SaleType;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Notifications\SavedSearchMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Tells members when a listing that just went public matches a search they saved.
 */
class SavedSearchMatcher
{
    /** Same numbering as the search page's type filter. */
    private const TYPE_FIXED = 3;

    public function __construct(private readonly AuctionNotifier $notifier) {}

    /**
     * @return int how many members were notified
     */
    public function listingPublished(Listing $listing): int
    {
        if (! $listing->isPublished()) {
            return 0;
        }

        $type = $listing->sale_type === SaleType::Fixed ? self::TYPE_FIXED : $listing->auction_type?->value;
        // A search saved on a parent category also covers its sub-categories.
        $categories = array_filter([$listing->category_id, $listing->category?->parent_id]);
        // What a keyword is looked for in: as in the search page, the title and the brand.
        $title = self::fold($listing->title.' '.$listing->brand);

        $notified = [];

        SavedSearch::query()
            ->where('user_id', '!=', $listing->user_id)
            ->where(fn (Builder $query) => $query->whereNull('city_id')->orWhere('city_id', $listing->city_id))
            ->where(fn (Builder $query) => $query->whereNull('type')->orWhere('type', $type))
            ->where(fn (Builder $query) => $query->whereNull('category_id')->orWhereIn('category_id', $categories))
            ->with(['user', 'category', 'city'])
            ->oldest('id')
            ->each(function (SavedSearch $search) use ($listing, $title, &$notified): void {
                // One notice per member, even when several of their searches match.
                if (isset($notified[$search->user_id])
                    || ($search->q !== null && ! str_contains($title, self::fold($search->q)))
                    || $search->user->isBlocked()) {
                    return;
                }

                $notified[$search->user_id] = true;
                $this->notifier->send($search->user, new SavedSearchMatch($listing, $search));
            });

        return count($notified);
    }

    /**
     * Lower case without accents, so "xe dap" finds "Xe đạp".
     */
    private static function fold(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }
}

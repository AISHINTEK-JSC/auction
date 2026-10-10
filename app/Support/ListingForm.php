<?php

namespace App\Support;

use App\Enums\ItemCondition;
use App\Models\Listing;

/**
 * What the listing forms (create, edit, relist, draft) need from the server.
 */
class ListingForm
{
    /**
     * @return array{conditions: list<array{value: string, label: string}>, brands: list<string>}
     */
    public static function options(): array
    {
        return [
            'conditions' => array_map(
                fn (ItemCondition $condition) => ['value' => $condition->value, 'label' => $condition->label()],
                ItemCondition::cases(),
            ),
            // Suggested while typing the (optional) brand.
            'brands' => Brand::suggestions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function createOptions(): array
    {
        return self::options() + [
            'fixedListingDays' => MarketplaceSettings::fixedListingDays(),
        ];
    }

    /**
     * The fields of a listing its seller can change. Expects images and tags to be loaded.
     *
     * @return array<string, mixed>
     */
    public static function editable(Listing $listing): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'description' => $listing->description ?? '',
            'category_id' => $listing->category_id,
            'city_id' => $listing->city_id,
            'condition' => $listing->condition->value,
            'brand' => $listing->brand ?? '',
            'tags' => $listing->tags->pluck('name')->all(),
            // The form shows them as small squares: the thumbnail will do.
            'images' => $listing->images->map(fn ($image) => ['id' => $image->id, 'url' => $image->thumb_url])->all(),
        ];
    }

    /**
     * The sale settings of a listing, as the create form's fields. Used to
     * prefill it when relisting or continuing a draft.
     *
     * @return array<string, mixed>
     */
    public static function sale(Listing $listing): array
    {
        return [
            'sale_type' => $listing->sale_type->value,
            'is_negotiable' => $listing->is_negotiable,
            'auction_type' => $listing->auction_type?->value,
            'start_price' => $listing->start_price > 0 ? $listing->start_price : null,
            'min_bid_price' => $listing->min_bid_price,
            'bid_step' => $listing->bid_step,
            'auto_extend' => $listing->auto_extend,
            'down_minutes' => $listing->down_time ? intdiv($listing->down_time, 60) : null,
            'down_price' => $listing->down_price,
            'finish_price' => $listing->finish_price,
        ];
    }
}

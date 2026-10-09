<?php

namespace App\Support;

use App\Enums\AuctionStatus;
use App\Models\Listing;
use App\Models\User;

/**
 * Shapes listings for the Inertia pages (see resources/js/types/marketplace.ts).
 */
class ListingPresenter
{
    /**
     * Expects images and city to be eager loaded.
     *
     * @return array<string, mixed>
     */
    public static function card(Listing $listing): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'image' => $listing->images->first()?->url,
            'city' => $listing->city?->name,
            'auction_type' => $listing->auction_type->value,
            'status' => $listing->auction_status->value,
            'status_label' => $listing->auction_status->label(),
            'current_price' => $listing->current_price,
            'bid_count' => $listing->bid_count,
            'starts_at' => $listing->start_time?->toIso8601String(),
            'ends_at' => $listing->ends_at?->toIso8601String(),
            'winner' => $listing->auction_status === AuctionStatus::Completed ? $listing->winner?->name : null,
            'winning_price' => $listing->winning_price,
        ];
    }

    /**
     * Expects images, city, category, tags and user to be eager loaded.
     *
     * @return array<string, mixed>
     */
    public static function detail(Listing $listing, ?User $viewer): array
    {
        $isOwner = $viewer !== null && $viewer->id === $listing->user_id;

        return [
            ...self::card($listing),
            'description' => $listing->description,
            'condition' => $listing->condition->label(),
            'images' => $listing->images->map(fn ($image) => $image->url)->all(),
            'category' => $listing->category
                ? ['name' => $listing->category->name, 'slug' => $listing->category->slug]
                : null,
            'tags' => $listing->tags->pluck('name')->all(),
            'auction_type_label' => $listing->auction_type->label(),
            'start_price' => $listing->start_price,
            // The reserve price is the seller's secret.
            'min_bid_price' => $isOwner ? $listing->min_bid_price : null,
            'auto_extend' => $listing->auto_extend,
            'down_time' => $listing->down_time,
            'down_price' => $listing->down_price,
            'finish_price' => $listing->finish_price,
            'created_at' => $listing->created_at?->toIso8601String(),
            'is_owner' => $isOwner,
            'seller' => self::seller($listing->user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function seller(User $user): array
    {
        $listings = $user->listings()->published();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'joined_at' => $user->created_at?->toIso8601String(),
            'has_phone' => $user->phone !== null,
            'has_address' => $user->address !== null,
            'listing_count' => (clone $listings)->count(),
            'sold_count' => (clone $listings)->status(AuctionStatus::Completed)->count(),
        ];
    }
}

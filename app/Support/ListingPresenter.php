<?php

namespace App\Support;

use App\Enums\AuctionStatus;
use App\Models\Listing;
use App\Models\Review;
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
            'has_auction' => $listing->auction_id !== null,
            'leader_id' => $listing->leader_id,
            'moderation' => $listing->moderation_status->value,
            'moderation_label' => $listing->moderation_status->label(),
            'moderation_note' => $listing->moderation_note,
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
        $settles = $viewer !== null && $viewer->can('settle', $listing);

        return [
            ...self::card($listing),
            'description' => $listing->description,
            'condition' => $listing->condition->label(),
            'images' => $listing->images->map(fn ($image) => $image->url)->all(),
            'category' => $listing->category
                ? ['name' => $listing->category->name, 'slug' => $listing->category->slug]
                : null,
            'tags' => $listing->tags->map(fn ($tag) => ['name' => $tag->name, 'slug' => $tag->slug])->all(),
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
            'is_winner' => $viewer !== null && $viewer->id === $listing->winner_id,
            'seller' => self::seller($listing->user),
            'watching' => $viewer !== null && $listing->watchers()->whereKey($viewer->id)->exists(),
            'watcher_count' => $listing->watchers()->count(),
            'can' => [
                'edit' => $isOwner,
                'settle' => $settles,
            ],
            // Only the seller and the winner get the other side's contact details.
            'contact' => $settles ? self::contact($isOwner ? $listing->winner : $listing->user) : null,
            'my_review' => $settles
                ? self::review($listing->reviews()->where('reviewer_id', $viewer->id)->first())
                : null,
        ];
    }

    /**
     * Public facts about a member, for the seller card and the profile page.
     *
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
            'rating' => round((float) $user->reviewsReceived()->avg('rating'), 1),
            'review_count' => $user->reviewsReceived()->count(),
        ];
    }

    /**
     * @return array{name: string, email: string, phone: string|null, address: string|null}|null
     */
    public static function contact(?User $user): ?array
    {
        return $user === null ? null : [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function review(?Review $review): ?array
    {
        return $review === null ? null : [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'created_at' => $review->created_at?->toIso8601String(),
            'reviewer' => $review->relationLoaded('reviewer') ? $review->reviewer->name : null,
            'listing' => $review->relationLoaded('listing') ? $review->listing->title : null,
        ];
    }
}

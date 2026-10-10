<?php

namespace App\Support;

use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
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
            'image' => $listing->images->first()?->thumb_url,
            'city' => $listing->city?->name,
            'sale_type' => $listing->sale_type->value,
            'auction_type' => $listing->auction_type?->value,
            'status' => $listing->auction_status->value,
            'status_label' => $listing->statusLabel(),
            'current_price' => $listing->current_price,
            'negotiable' => $listing->is_negotiable,
            'bid_count' => $listing->bid_count,
            'view_count' => $listing->view_count,
            'starts_at' => $listing->start_time?->toIso8601String(),
            'ends_at' => $listing->ends_at?->toIso8601String(),
            'winner' => $listing->auction_status === AuctionStatus::Completed ? $listing->winner?->name : null,
            'winning_price' => $listing->winning_price,
            'has_auction' => $listing->isLive(),
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
            'brand' => $listing->brand,
            'images' => $listing->images->map(fn ($image) => ['url' => $image->url, 'thumb' => $image->thumb_url])->all(),
            'category' => $listing->category
                ? ['name' => $listing->category->name, 'slug' => $listing->category->slug]
                : null,
            'tags' => $listing->tags->map(fn ($tag) => ['name' => $tag->name, 'slug' => $tag->slug])->all(),
            'auction_type_label' => $listing->auction_type?->label() ?? $listing->sale_type->label(),
            'start_price' => $listing->start_price,
            // The reserve price is the seller's secret.
            // The amount is the seller's secret; that there is a floor is not, or
            // bidders could not make sense of a bid being turned away.
            'min_bid_price' => $isOwner ? $listing->min_bid_price : null,
            'has_reserve' => $listing->auction_type === AuctionType::Up && $listing->min_bid_price !== null,
            'bid_step' => $listing->bid_step,
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
                'cancel' => $viewer !== null && $viewer->can('cancel', $listing),
                'mark_sold' => $viewer !== null && $viewer->can('markSold', $listing),
                'renew' => $viewer !== null && $viewer->can('renew', $listing),
            ],
            'contact' => self::contactFor($listing, $viewer, $settles),
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
     * Who may see whose contact details:
     * - after an auction, the seller and the winner see each other's, and the
     *   seller also sees where the winner wants the item delivered;
     * - on a fixed-price listing that is for sale, signed-in members with a
     *   verified email see the seller's. Guests do not, to keep it from scrapers.
     *
     * @return array{name: string, email: string, phone: string|null, address: string|null, shipping: array{name: string, phone: string, address: string}|null}|null
     */
    private static function contactFor(Listing $listing, ?User $viewer, bool $settles): ?array
    {
        if ($settles) {
            return $viewer?->id === $listing->user_id
                ? self::contact($listing->winner, withShipping: true)
                : self::contact($listing->user);
        }

        if ($listing->isFixedPrice()
            && $listing->auction_status->isOpen()
            && $viewer !== null
            && $viewer->id !== $listing->user_id
            && $viewer->hasVerifiedEmail()) {
            return self::contact($listing->user);
        }

        return null;
    }

    /**
     * @param  bool  $withShipping  include the delivery address: only for a seller looking at their buyer
     * @return array{name: string, email: string, phone: string|null, address: string|null, shipping: array{name: string, phone: string, address: string}|null}|null
     */
    public static function contact(?User $user, bool $withShipping = false): ?array
    {
        if ($user === null) {
            return null;
        }

        $shipping = $withShipping ? $user->shippingAddress : null;

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'shipping' => $shipping === null ? null : [
                'name' => $shipping->recipient_name,
                'phone' => $shipping->phone,
                'address' => $shipping->oneLine(),
            ],
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

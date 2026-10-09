<?php

namespace App\Policies;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /**
     * The detail page needs an auction in the engine. Unpublished listings that
     * have one (hidden by a moderator) stay visible to their seller and to staff.
     */
    public function view(?User $user, Listing $listing): bool
    {
        if (! $listing->isLive()) {
            return false;
        }

        return $listing->isPublished()
            || ($user !== null && ($user->id === $listing->user_id || $user->isStaff()));
    }

    /**
     * Title, description, images and tags. The auction itself cannot be changed.
     */
    public function update(User $user, Listing $listing): bool
    {
        return $user->id === $listing->user_id;
    }

    /**
     * Only the seller may cancel the auction or take the post down, and only
     * while it is still open.
     */
    public function cancel(User $user, Listing $listing): bool
    {
        return $user->id === $listing->user_id
            && $listing->isLive()
            && $listing->auction_status->isOpen();
    }

    /**
     * A fixed-price listing is closed by its seller once the item is sold.
     */
    public function markSold(User $user, Listing $listing): bool
    {
        return $user->id === $listing->user_id
            && $listing->isFixedPrice()
            && $listing->auction_status->isOpen();
    }

    /**
     * Put the item up again: after an auction that ended without a winner, or
     * after a moderator rejected the listing.
     */
    public function relist(User $user, Listing $listing): bool
    {
        if ($user->id !== $listing->user_id) {
            return false;
        }

        if ($listing->moderation_status === ModerationStatus::Rejected) {
            return true;
        }

        return $listing->isLive()
            && ! $listing->auction_status->isOpen()
            && $listing->auction_status !== AuctionStatus::Completed;
    }

    /**
     * Both sides of a finished auction: they see each other's contact details,
     * may review each other, and either may declare that the winner backed out.
     */
    public function settle(User $user, Listing $listing): bool
    {
        return $listing->auction_status === AuctionStatus::Completed
            && $listing->winner_id !== null
            && ($user->id === $listing->winner_id || $user->id === $listing->user_id);
    }
}

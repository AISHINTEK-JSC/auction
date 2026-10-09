<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /**
     * Only the seller may cancel, and only while the auction is still open.
     */
    public function cancel(User $user, Listing $listing): bool
    {
        return $user->id === $listing->user_id && $listing->auction_status->isOpen();
    }
}

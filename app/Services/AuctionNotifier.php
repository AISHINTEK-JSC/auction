<?php

namespace App\Services;

use App\Enums\AuctionStatus;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\MarketplaceNotification;

/**
 * Tells members what happened in an auction. A failed email must never break
 * the bid or sync that triggered it, so every send is rescued.
 */
class AuctionNotifier
{
    public function send(?User $user, MarketplaceNotification $notification): void
    {
        if ($user === null) {
            return;
        }

        rescue(fn () => $user->notify($notification));
    }

    /**
     * The previous highest bidder lost the lead.
     */
    public function outbid(Listing $listing, int $previousLeaderId): void
    {
        $this->send(User::query()->find($previousLeaderId), MarketplaceNotification::outbid($listing));
    }

    /**
     * The auction left the open states. The webhook, the scheduler and page
     * views can all notice that at the same moment, so the listing is claimed
     * first and only the caller that wins the claim notifies.
     */
    public function closed(Listing $listing): void
    {
        $claimed = Listing::query()
            ->whereKey($listing->id)
            ->whereNull('closed_notified_at')
            ->update(['closed_notified_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $listing->closed_notified_at = now();
        $listing->syncOriginalAttribute('closed_notified_at');

        $winner = $listing->winner_id ? User::query()->find($listing->winner_id) : null;
        $others = $listing->bidders()->whereKeyNot($listing->winner_id ?? 0)->get();

        match ($listing->auction_status) {
            AuctionStatus::Completed => $this->completed($listing, $winner, $others),
            AuctionStatus::Expired => $this->expired($listing, $others),
            default => $others->each(fn (User $bidder) => $this->send($bidder, MarketplaceNotification::cancelled($listing))),
        };
    }

    /**
     * @param  iterable<User>  $others
     */
    private function completed(Listing $listing, ?User $winner, iterable $others): void
    {
        $this->send($listing->user, MarketplaceNotification::sold($listing, $winner));
        $this->send($winner, MarketplaceNotification::won($listing));

        foreach ($others as $bidder) {
            $this->send($bidder, MarketplaceNotification::lost($listing));
        }
    }

    /**
     * @param  iterable<User>  $others
     */
    private function expired(Listing $listing, iterable $others): void
    {
        $this->send($listing->user, MarketplaceNotification::unsold($listing));

        foreach ($others as $bidder) {
            $this->send($bidder, MarketplaceNotification::lost($listing));
        }
    }
}

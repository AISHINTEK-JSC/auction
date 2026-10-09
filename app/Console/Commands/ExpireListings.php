<?php

namespace App\Console\Commands;

use App\Enums\AuctionStatus;
use App\Enums\SaleType;
use App\Models\Listing;
use App\Notifications\MarketplaceNotification;
use App\Services\AuctionNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('listings:expire')]
#[Description('Close fixed-price listings whose time is up')]
class ExpireListings extends Command
{
    /**
     * Auctions end in the engine. Fixed-price listings have no engine behind
     * them, so their lifetime (config marketplace.fixed_listing_days) ends here.
     */
    public function handle(AuctionNotifier $notifier): int
    {
        $expired = 0;

        Listing::query()
            ->where('sale_type', SaleType::Fixed)
            ->whereIn('auction_status', AuctionStatus::openValues())
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->with('user')
            ->chunkById(200, function ($listings) use ($notifier, &$expired): void {
                foreach ($listings as $listing) {
                    $listing->forceFill(['auction_status' => AuctionStatus::Expired])->save();
                    $notifier->send($listing->user, MarketplaceNotification::expired($listing));
                    $expired++;
                }
            });

        $this->info("Expired {$expired} fixed-price listings.");

        return self::SUCCESS;
    }
}

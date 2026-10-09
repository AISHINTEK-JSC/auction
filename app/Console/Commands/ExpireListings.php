<?php

namespace App\Console\Commands;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Enums\SaleType;
use App\Models\Listing;
use App\Notifications\MarketplaceNotification;
use App\Services\AuctionNotifier;
use App\Support\MarketplaceSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('listings:expire')]
#[Description('Close fixed-price listings whose time is up, and remind sellers shortly before')]
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
            ->where('moderation_status', '!=', ModerationStatus::Draft)
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

        $reminded = $this->remind($notifier);

        $this->info("Expired {$expired} fixed-price listings, reminded {$reminded} sellers.");

        return self::SUCCESS;
    }

    /**
     * Tell sellers, once per lifetime, that a listing is about to expire, so
     * they can renew it in time.
     */
    private function remind(AuctionNotifier $notifier): int
    {
        $days = MarketplaceSettings::expiryReminderDays();

        if ($days === 0) {
            return 0;
        }

        $reminded = 0;

        Listing::query()
            ->published()
            ->where('sale_type', SaleType::Fixed)
            ->whereIn('auction_status', AuctionStatus::openValues())
            ->whereNull('expiry_reminded_at')
            ->whereBetween('ends_at', [now(), now()->addDays($days)])
            ->with('user')
            ->chunkById(200, function ($listings) use ($notifier, &$reminded): void {
                foreach ($listings as $listing) {
                    $listing->forceFill(['expiry_reminded_at' => now()])->save();
                    $notifier->send($listing->user, MarketplaceNotification::expiringSoon($listing));
                    $reminded++;
                }
            });

        return $reminded;
    }
}

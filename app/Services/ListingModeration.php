<?php

namespace App\Services;

use App\Actions\Listings\CreateListing;
use App\Enums\AuctionType;
use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Notifications\MarketplaceNotification;
use App\Services\AuctionEngine\EngineException;
use App\Support\AdminLog;
use DomainException;

/**
 * What moderators do to a listing. A listing that waited for approval has no
 * auction yet; approving it is what opens the auction in the engine.
 */
class ListingModeration
{
    public function __construct(
        private readonly CreateListing $creator,
        private readonly ListingSyncService $sync,
        private readonly AuctionNotifier $notifier,
        private readonly SavedSearchMatcher $savedSearches,
    ) {}

    /**
     * @throws DomainException when the planned times have already passed
     * @throws EngineException
     */
    public function approve(Listing $listing): void
    {
        if ($listing->isAuction() && $listing->auction_id === null) {
            // The seller's start time may have passed while the listing waited: start now instead.
            if ($listing->start_time?->isPast()) {
                $listing->start_time = null;
            }

            if ($listing->auction_type === AuctionType::Up
                && $listing->end_time?->lt(($listing->start_time ?? now())->addMinutes(5))) {
                throw new DomainException('Thời gian kết thúc của tin đã qua hoặc còn dưới 5 phút. Hãy từ chối để người bán đăng lại.');
            }

            $this->creator->openAuction($listing);
        }

        // A fixed-price listing's lifetime starts when it becomes visible.
        if ($listing->isFixedPrice() && $listing->moderation_status === ModerationStatus::Pending) {
            $listing->ends_at = CreateListing::fixedListingDeadline();
        }

        $firstPublication = $listing->moderation_status === ModerationStatus::Pending;

        $this->decide($listing, ModerationStatus::Approved, null);

        if ($firstPublication) {
            rescue(fn () => $this->savedSearches->listingPublished($listing));
        }

        if ($listing->isAuction()) {
            rescue(fn () => $this->sync->refresh($listing), report: false);
        }
    }

    public function reject(Listing $listing, ?string $note): void
    {
        $this->decide($listing, ModerationStatus::Rejected, $note);
    }

    /**
     * Take a published listing off the site. Its auction keeps running in the
     * engine; cancel it separately if it has to stop.
     */
    public function hide(Listing $listing, ?string $note): void
    {
        $this->decide($listing, ModerationStatus::Hidden, $note);
    }

    private function decide(Listing $listing, ModerationStatus $status, ?string $note): void
    {
        $listing->moderation_status = $status;
        $listing->moderation_note = $note;
        $listing->save();

        // Only staff moderate; outside a signed-in request (tests, console) there is nobody to name.
        if (auth()->check()) {
            AdminLog::record('listing.'.$status->value, $listing, sprintf(
                '%s tin #%d "%s"%s',
                $status->label(),
                $listing->id,
                $listing->title,
                $note ? ' — lý do: '.$note : '',
            ));
        }

        $this->notifier->send($listing->user, MarketplaceNotification::moderated($listing));
    }
}

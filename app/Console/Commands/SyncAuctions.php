<?php

namespace App\Console\Commands;

use App\Enums\AuctionStatus;
use App\Models\Listing;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use App\Services\ListingSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('auctions:sync')]
#[Description('Refresh the snapshot of every open listing from the auction engine')]
class SyncAuctions extends Command
{
    /**
     * The engine's webhook is not enough on its own: it is not sent for auctions
     * that start immediately, for price drops of descending auctions, or for any
     * extension after the first. This runs every minute, like the engine's own scheduler.
     */
    public function handle(AuctionEngineClient $engine, ListingSyncService $sync): int
    {
        $listings = Listing::query()
            ->published()
            ->whereNotNull('auction_id')
            ->whereIn('auction_status', AuctionStatus::openValues())
            ->get();

        if ($listings->isEmpty()) {
            return self::SUCCESS;
        }

        try {
            $auctions = collect($engine->listAuctions())->keyBy('auction_id');
        } catch (EngineException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $updated = 0;

        foreach ($listings as $listing) {
            $auction = $auctions->get($listing->auction_id);

            if ($auction === null) {
                continue;
            }

            try {
                $sync->apply($listing, $auction);
                $sync->forget($listing);
                $updated++;
            } catch (EngineException $e) {
                $this->warn("Listing {$listing->id}: {$e->getMessage()}");
            }
        }

        $this->info("Synced {$updated} of {$listings->count()} open listings.");

        return self::SUCCESS;
    }
}

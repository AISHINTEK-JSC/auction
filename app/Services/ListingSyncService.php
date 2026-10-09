<?php

namespace App\Services;

use App\Enums\AuctionStatus;
use App\Models\Listing;
use App\Models\User;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps the snapshot columns on listings in step with the auction engine.
 *
 * List pages read the snapshot (so they can filter, sort and paginate in SQL);
 * the detail page reads the engine through a few seconds of cache, because the
 * engine throttles per IP and every visitor shares this server's IP.
 */
class ListingSyncService
{
    private const LIVE_TTL_SECONDS = 3;

    public function __construct(
        private readonly AuctionEngineClient $engine,
        private readonly AuctionNotifier $notifier,
    ) {}

    /**
     * Current engine state for the detail page. Falls back to the snapshot when
     * the engine cannot be reached.
     *
     * @return array{status: int, status_label: string, current_price: int, bid_count: int, remaining_time: int|null, highest_bidder_id: int|null, stale: bool}
     */
    public function liveState(Listing $listing): array
    {
        try {
            $auction = Cache::remember(
                $this->cacheKey($listing, 'state'),
                self::LIVE_TTL_SECONDS,
                function () use ($listing) {
                    $auction = $this->engine->getAuction((int) $listing->auction_id);
                    $this->apply($listing, $auction);

                    return $auction;
                },
            );
        } catch (EngineException) {
            return [
                'status' => $listing->auction_status->value,
                'status_label' => $listing->auction_status->label(),
                'current_price' => $listing->current_price,
                'bid_count' => $listing->bid_count,
                'remaining_time' => $listing->auction_status === AuctionStatus::Running && $listing->ends_at
                    ? max(0, (int) now()->diffInSeconds($listing->ends_at, false))
                    : null,
                'highest_bidder_id' => null,
                'stale' => true,
            ];
        }

        $status = $this->statusOf($auction) ?? $listing->auction_status;

        return [
            'status' => $status->value,
            'status_label' => $status->label(),
            'current_price' => (int) $auction['current_price'],
            'bid_count' => (int) ($auction['bid_number'] ?? 0),
            'remaining_time' => isset($auction['remaining_time']) ? (int) $auction['remaining_time'] : null,
            'highest_bidder_id' => isset($auction['highest_bidder_id']) ? (int) $auction['highest_bidder_id'] : null,
            'stale' => false,
        ];
    }

    /**
     * Bid history, newest first, through the same short cache.
     *
     * @return list<array<string, mixed>>
     */
    public function liveBids(Listing $listing): array
    {
        try {
            $bids = Cache::remember(
                $this->cacheKey($listing, 'bids'),
                self::LIVE_TTL_SECONDS,
                fn () => $this->engine->getBids((int) $listing->auction_id),
            );
        } catch (EngineException) {
            return [];
        }

        return array_values(collect($bids)
            ->reject(fn (array $bid) => (bool) ($bid['is_cancel'] ?? false))
            ->sortByDesc('bid_id')
            ->all());
    }

    /**
     * Pull the engine state now, bypassing the cache. Call after anything that
     * changes the auction (create, bid, cancel, webhook).
     */
    public function refresh(Listing $listing): void
    {
        $this->forget($listing);
        $this->apply($listing, $this->engine->getAuction((int) $listing->auction_id));
    }

    public function forget(Listing $listing): void
    {
        Cache::forget($this->cacheKey($listing, 'state'));
        Cache::forget($this->cacheKey($listing, 'bids'));
    }

    /**
     * Write an engine auction (detail or list summary) into the snapshot columns.
     *
     * @param  array<string, mixed>  $auction
     */
    public function apply(Listing $listing, array $auction): void
    {
        $status = $this->statusOf($auction) ?? $listing->auction_status;

        $listing->auction_status = $status;
        $listing->current_price = (int) $auction['current_price'];
        $listing->bid_count = (int) ($auction['bid_number'] ?? $listing->bid_count);
        $listing->synced_at = now();

        // Only the detail endpoint reports the leader; list summaries leave it as it was.
        if (array_key_exists('highest_bidder_id', $auction)) {
            $listing->leader_id = $auction['highest_bidder_id'] === null ? null : (int) $auction['highest_bidder_id'];
        }

        if ($status === AuctionStatus::Running && isset($auction['remaining_time'])) {
            // end_time does not move when an ascending auction is extended; remaining_time does.
            $listing->ends_at = now()->addSeconds((int) $auction['remaining_time']);
        } elseif ($status === AuctionStatus::Upcoming) {
            $listing->ends_at = $listing->end_time;
        } elseif (! $status->isOpen() && ($listing->ends_at === null || $listing->ends_at->isFuture())) {
            $listing->ends_at = now();
        }

        if ($status === AuctionStatus::Completed && $listing->winner_id === null) {
            // List summaries carry the winner; the detail endpoint does not.
            $winner = array_key_exists('bid_winner', $auction)
                ? $auction['bid_winner']
                : $this->engine->getWinner((int) $listing->auction_id);

            if (is_array($winner) && User::query()->whereKey((int) $winner['bidder_id'])->exists()) {
                $listing->winner_id = (int) $winner['bidder_id'];
                $listing->winning_price = (int) $winner['bid_price'];
            }
        }

        $listing->save();

        if (! $status->isOpen() && $listing->closed_notified_at === null) {
            $this->notifier->closed($listing);
        }
    }

    /**
     * @param  array<string, mixed>  $auction
     */
    private function statusOf(array $auction): ?AuctionStatus
    {
        $status = data_get($auction, 'status.status');

        return $status === null ? null : AuctionStatus::tryFrom((int) $status);
    }

    private function cacheKey(Listing $listing, string $kind): string
    {
        return "auction.{$listing->auction_id}.{$kind}";
    }
}

<?php

namespace App\Services;

use App\Models\Forfeit;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use Carbon\CarbonInterface;

/**
 * What happens to a winner who gives up an auction result. The first time
 * within the window is a warning; from then on it costs a ban from bidding
 * that grows with each repeat (config/marketplace.php).
 */
class ForfeitPenalty
{
    public function __construct(private readonly AuctionNotifier $notifier) {}

    /**
     * Record the forfeit and apply whatever penalty it earns.
     *
     * @param  User|null  $declaredBy  the winner, or the seller reporting that the winner did not buy
     * @return CarbonInterface|null until when the member is banned from bidding, if they are
     */
    public function apply(User $winner, Listing $listing, ?User $declaredBy): ?CarbonInterface
    {
        Forfeit::query()->create([
            'user_id' => $winner->id,
            'listing_id' => $listing->id,
            'declared_by' => $declaredBy?->id,
        ]);

        $count = $this->recentCount($winner);
        $days = $this->banDaysFor($count);

        if ($days === null) {
            $this->notifier->send($winner, MarketplaceNotification::forfeitWarning($listing, $this->nextBanDays($count)));

            return null;
        }

        // Never shorten a ban that is already running.
        $until = now()->addDays($days);
        if ($winner->bid_banned_until === null || $winner->bid_banned_until->lt($until)) {
            $winner->forceFill(['bid_banned_until' => $until])->save();
        }

        $this->notifier->send($winner, MarketplaceNotification::bidBan($listing, $winner->bid_banned_until, $count));

        return $winner->bid_banned_until;
    }

    /**
     * Forfeits that still count against the member.
     */
    public function recentCount(User $user): int
    {
        return $user->forfeits()
            ->where('created_at', '>=', now()->subDays((int) config('marketplace.forfeit_window_days')))
            ->count();
    }

    /**
     * Days of ban for the Nth forfeit, or null when it is only a warning.
     */
    private function banDaysFor(int $count): ?int
    {
        $bans = $this->bans();
        $reached = array_filter(array_keys($bans), fn (int $threshold) => $threshold <= $count);

        return $reached === [] ? null : $bans[max($reached)];
    }

    private function nextBanDays(int $count): ?int
    {
        return $this->banDaysFor($count + 1);
    }

    /**
     * @return array<int, int>
     */
    private function bans(): array
    {
        return array_map('intval', (array) config('marketplace.forfeit_bans', []));
    }
}

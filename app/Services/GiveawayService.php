<?php

namespace App\Services;

use App\Models\Giveaway;
use App\Models\GiveawayEntry;
use App\Models\User;
use App\Notifications\GiveawayAnnouncement;
use App\Notifications\MarketplaceNotification;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class GiveawayService
{
    public function __construct(private readonly AuctionNotifier $notifier) {}

    /**
     * Tell every member who could join that the giveaway is open: verified,
     * not blocked, and not the admin who just created it.
     *
     * @return int how many members were notified
     */
    public function announce(Giveaway $giveaway, ?User $except = null): int
    {
        if (! $giveaway->isOpen()) {
            return 0;
        }

        $notified = 0;

        User::query()
            ->whereNull('blocked_at')
            ->whereNotNull('email_verified_at')
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->chunkById(500, function ($members) use ($giveaway, &$notified): void {
                rescue(fn () => Notification::send($members, new GiveawayAnnouncement($giveaway)));

                $notified += $members->count();
            });

        return $notified;
    }

    /**
     * Sign a member up. Each member can enter a giveaway once; they take part
     * with their permanent member number.
     *
     * @throws DomainException when the giveaway no longer takes entries
     */
    public function join(Giveaway $giveaway, User $user): GiveawayEntry
    {
        if (! $giveaway->isOpen()) {
            throw new DomainException('Chương trình đã hết hạn đăng ký.');
        }

        return GiveawayEntry::query()->firstOrCreate([
            'giveaway_id' => $giveaway->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Pick the winners at random among the entries. A giveaway is drawn once.
     *
     * @return Collection<int, GiveawayEntry>
     *
     * @throws DomainException when it was already drawn or nobody entered
     */
    public function draw(Giveaway $giveaway): Collection
    {
        $winners = DB::transaction(function () use ($giveaway) {
            $giveaway = Giveaway::query()->lockForUpdate()->findOrFail($giveaway->id);

            if ($giveaway->isDrawn()) {
                throw new DomainException('Chương trình này đã quay thưởng rồi.');
            }

            // Blocked members stay in the list but cannot win.
            $eligible = $giveaway->entries()
                ->whereHas('user', fn ($query) => $query->whereNull('blocked_at'))
                ->pluck('id');

            if ($eligible->isEmpty()) {
                throw new DomainException('Chưa có ai đăng ký tham gia.');
            }

            // random_int-backed shuffle: fair, and the same on every database.
            $picked = $eligible->shuffle()->take($giveaway->winner_count);

            GiveawayEntry::query()->whereKey($picked)->update(['is_winner' => true]);
            $giveaway->forceFill(['drawn_at' => now()])->save();

            return GiveawayEntry::query()->whereKey($picked)->with('user')->get();
        });

        $giveaway->refresh();

        foreach ($winners as $entry) {
            $this->notifier->send($entry->user, MarketplaceNotification::giveawayWon($giveaway));
        }

        return $winners;
    }
}

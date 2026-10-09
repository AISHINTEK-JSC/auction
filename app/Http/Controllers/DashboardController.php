<?php

namespace App\Http\Controllers;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Models\User;
use App\Support\ListingPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The signed-in member's auctions: selling, bidding on, following, won,
     * ended, and waiting for (or refused by) the moderators.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $open = AuctionStatus::openValues();

        return Inertia::render('Dashboard', [
            'seller' => ListingPresenter::seller($user),
            'selling' => $this->cards($user, Listing::query()->published()->whereBelongsTo($user)->whereIn('auction_status', $open)),
            'bidding' => $this->cards($user, $user->bidListings()->published()->whereIn('auction_status', $open)),
            'watching' => $this->cards($user, $user->watchedListings()->published()),
            'won' => $this->cards($user, Listing::query()->published()->where('winner_id', $user->id)),
            'ended' => $this->cards($user, Listing::query()->published()->whereBelongsTo($user)->whereNotIn('auction_status', $open)),
            'review' => $this->cards($user, Listing::query()->whereBelongsTo($user)->where('moderation_status', '!=', ModerationStatus::Approved)),
        ]);
    }

    /**
     * @param  Builder<Listing>|BelongsToMany<Listing, User>  $query
     * @return list<array<string, mixed>>
     */
    private function cards(User $user, Builder|BelongsToMany $query): array
    {
        return array_values($query
            ->with(['images', 'city', 'winner'])
            ->latest('listings.id')
            ->limit(50)
            ->get()
            ->map(fn (Listing $listing) => [
                ...ListingPresenter::card($listing),
                'leading' => $listing->leader_id === $user->id,
                'can' => [
                    'edit' => $user->can('update', $listing),
                    'cancel' => $user->can('cancel', $listing),
                    'relist' => $user->can('relist', $listing),
                ],
            ])
            ->all());
    }
}

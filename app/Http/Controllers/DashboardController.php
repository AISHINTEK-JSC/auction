<?php

namespace App\Http\Controllers;

use App\Enums\AuctionStatus;
use App\Models\Listing;
use App\Support\ListingPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The signed-in user's own auctions: selling, ended and won.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'seller' => ListingPresenter::seller($user),
            'selling' => $this->cards(Listing::query()->whereBelongsTo($user)->whereIn('auction_status', AuctionStatus::openValues())),
            'ended' => $this->cards(Listing::query()->whereBelongsTo($user)->whereNotIn('auction_status', AuctionStatus::openValues())),
            'won' => $this->cards(Listing::query()->where('winner_id', $user->id)),
        ]);
    }

    /**
     * @param  Builder<Listing>  $query
     * @return list<array<string, mixed>>
     */
    private function cards(Builder $query): array
    {
        return array_values($query
            ->published()
            ->with(['images', 'city', 'winner'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Listing $listing) => ListingPresenter::card($listing))
            ->all());
    }
}

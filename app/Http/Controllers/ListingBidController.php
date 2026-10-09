<?php

namespace App\Http\Controllers;

use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
use App\Enums\BidType;
use App\Http\Requests\Listings\PlaceBidRequest;
use App\Models\Listing;
use App\Models\User;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use App\Services\AuctionNotifier;
use App\Services\ListingSyncService;
use App\Support\BidPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ListingBidController extends Controller
{
    /**
     * Price history of an auction.
     */
    public function index(Request $request, Listing $listing, ListingSyncService $sync): Response
    {
        abort_unless(Gate::forUser($request->user())->allows('view', $listing), 404);

        return Inertia::render('listings/Bids', [
            'listing' => ['id' => $listing->id, 'title' => $listing->title],
            'bids' => BidPresenter::present(
                $sync->liveBids($listing),
                $request->user(),
                $listing->auction_status === AuctionStatus::Running,
            ),
        ]);
    }

    public function store(
        PlaceBidRequest $request,
        Listing $listing,
        AuctionEngineClient $engine,
        ListingSyncService $sync,
        AuctionNotifier $notifier,
    ): RedirectResponse {
        abort_unless($listing->isPublished(), 404);

        $bidder = $request->user();

        if ($bidder->id === $listing->user_id) {
            throw ValidationException::withMessages(['bid_price' => __('engine.E_109')]);
        }

        $type = BidType::from((int) $request->validated('bid_type'));
        $price = $type === BidType::Limit ? (int) $request->validated('bid_price') : null;
        $previousLeader = $listing->leader_id;
        $error = null;

        if ($listing->auction_type === AuctionType::Up && $listing->bid_step !== null) {
            [$type, $price] = $this->applyBidStep($listing, $type, $price, $sync);
        }

        try {
            $engine->placeBid((int) $listing->auction_id, $bidder->id, $type->value, $price);
        } catch (EngineException $e) {
            $error = $e->getMessage();
        }

        // Refresh even after a rejection: the engine can extend the auction or
        // raise the visible price on a bid it then refuses.
        rescue(fn () => $sync->refresh($listing), fn () => $sync->forget($listing), report: false);

        if ($error !== null) {
            throw ValidationException::withMessages(['bid_price' => $error]);
        }

        // Feeds "auctions I take part in". For a quick bid the engine picked the
        // price, which is the current price right after the refresh.
        $listing->bidders()->syncWithoutDetaching([$bidder->id => [
            'last_bid_price' => $price ?? $listing->current_price,
        ]]);

        if ($previousLeader !== null && $previousLeader !== $bidder->id && $listing->leader_id === $bidder->id) {
            $notifier->outbid($listing, $previousLeader);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đặt giá thành công.']);

        return back();
    }

    /**
     * The seller's minimum raise. The engine knows nothing about it (its own
     * quick bid adds a fixed 1, 10 or 100 đ), so it is enforced here: a typed
     * bid must reach "current price + step", and a quick bid is placed at
     * exactly that price. The opening bid may equal the start price.
     *
     * @return array{BidType, int}
     */
    private function applyBidStep(Listing $listing, BidType $type, ?int $price, ListingSyncService $sync): array
    {
        $state = $sync->liveState($listing);
        $minimum = $state['current_price'] + ($state['highest_bidder_id'] !== null ? (int) $listing->bid_step : 0);

        if ($type === BidType::Market) {
            return [BidType::Limit, $minimum];
        }

        if ($price < $minimum) {
            throw ValidationException::withMessages(['bid_price' => sprintf(
                'Giá đặt tối thiểu là %s đ (bước giá %s đ).',
                number_format($minimum, 0, ',', '.'),
                number_format((int) $listing->bid_step, 0, ',', '.'),
            )]);
        }

        return [BidType::Limit, (int) $price];
    }

    /**
     * Withdraw one of your own bids while the auction is still running.
     */
    public function destroy(Request $request, Listing $listing, AuctionEngineClient $engine, ListingSyncService $sync): RedirectResponse
    {
        abort_unless($listing->isPublished(), 404);

        $data = $request->validate(['bid_price' => ['required', 'integer', 'gt:0']]);
        $bidder = $request->user();

        try {
            $engine->cancelBid((int) $listing->auction_id, $bidder->id, (int) $data['bid_price']);
        } catch (EngineException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        rescue(fn () => $sync->refresh($listing), fn () => $sync->forget($listing), report: false);

        $this->forgetBidderWithoutBids($listing, $bidder, $sync);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã huỷ lượt đặt giá.']);

        return back();
    }

    /**
     * After a withdrawal, keep "auctions I take part in" truthful: the member's
     * highest remaining bid, or no row at all once they have none left.
     */
    private function forgetBidderWithoutBids(Listing $listing, User $bidder, ListingSyncService $sync): void
    {
        $prices = array_map(
            fn (array $bid) => (int) $bid['bid_price'],
            array_filter($sync->liveBids($listing), fn (array $bid) => (int) $bid['bidder_id'] === $bidder->id),
        );

        if ($prices === []) {
            $listing->bidders()->detach($bidder->id);

            return;
        }

        $listing->bidders()->syncWithoutDetaching([$bidder->id => ['last_bid_price' => max($prices)]]);
    }
}

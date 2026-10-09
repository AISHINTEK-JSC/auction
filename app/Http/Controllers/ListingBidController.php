<?php

namespace App\Http\Controllers;

use App\Enums\BidType;
use App\Http\Requests\Listings\PlaceBidRequest;
use App\Models\Listing;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use App\Services\ListingSyncService;
use App\Support\BidPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ListingBidController extends Controller
{
    /**
     * Price history of an auction.
     */
    public function index(Listing $listing, ListingSyncService $sync): Response
    {
        abort_if($listing->auction_id === null, 404);

        return Inertia::render('listings/Bids', [
            'listing' => ['id' => $listing->id, 'title' => $listing->title],
            'bids' => BidPresenter::present($sync->liveBids($listing)),
        ]);
    }

    public function store(PlaceBidRequest $request, Listing $listing, AuctionEngineClient $engine, ListingSyncService $sync): RedirectResponse
    {
        abort_if($listing->auction_id === null, 404);

        $bidder = $request->user();

        if ($bidder->id === $listing->user_id) {
            throw ValidationException::withMessages(['bid_price' => __('engine.E_109')]);
        }

        $type = BidType::from((int) $request->validated('bid_type'));
        $error = null;

        try {
            $engine->placeBid(
                (int) $listing->auction_id,
                $bidder->id,
                $type->value,
                $type === BidType::Limit ? (int) $request->validated('bid_price') : null,
            );
        } catch (EngineException $e) {
            $error = $e->getMessage();
        }

        // Refresh even after a rejection: the engine can extend the auction or
        // raise the visible price on a bid it then refuses.
        rescue(fn () => $sync->refresh($listing), fn () => $sync->forget($listing), report: false);

        if ($error !== null) {
            throw ValidationException::withMessages(['bid_price' => $error]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đặt giá thành công.']);

        return back();
    }
}

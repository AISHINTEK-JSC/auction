<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Services\AuctionEngine\EngineException;
use App\Services\ListingSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

class AuctionWebhookController extends Controller
{
    private const RECHECK_DELAY_SECONDS = 2;

    /**
     * Receives the engine's push (auction started / extended / ended).
     *
     * The payload only tells us which auction changed; the state itself is
     * re-read from the engine, so a forged or stale push cannot write prices.
     */
    public function __invoke(Request $request, ListingSyncService $sync): JsonResponse
    {
        $key = (string) config('services.auction_engine.push_key');

        if ($key === '' || ! hash_equals($key, (string) $request->bearerToken())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // The engine sends GET with form_params, so the data sits in the body, not the query string.
        parse_str($request->getContent(), $body);
        $data = array_merge($body, $request->all());

        $auctionId = data_get($data, 'auction.auction_id');

        if (! is_numeric($auctionId)) {
            return response()->json(['success' => false, 'message' => 'auction_id is required'], 400);
        }

        $listing = Listing::query()->where('auction_id', (int) $auctionId)->first();

        if ($listing === null) {
            return response()->json(['success' => false, 'message' => 'Listing not found'], 404);
        }

        Log::info('Auction push', ['argument' => $data['argument'] ?? null, 'auction_id' => (int) $auctionId]);

        try {
            $sync->refresh($listing);
        } catch (EngineException $e) {
            // auctions:sync picks it up within a minute.
            Log::warning('Auction push: refresh failed', ['auction_id' => (int) $auctionId, 'error' => $e->getMessage()]);
        }

        // The engine pushes "ended" before it stores the final status, and it waits
        // for this response to do so. If the auction still looks open although its
        // time is up, look again once the response is out.
        if ($listing->auction_status->isOpen() && $listing->ends_at?->isPast()) {
            defer(function () use ($sync, $listing): void {
                Sleep::for(self::RECHECK_DELAY_SECONDS)->seconds();

                rescue(fn () => $sync->refresh($listing), report: false);
            });
        }

        return response()->json(['success' => true, 'listing_id' => $listing->id]);
    }
}

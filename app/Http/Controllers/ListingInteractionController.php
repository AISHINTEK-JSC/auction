<?php

namespace App\Http\Controllers;

use App\Enums\AuctionStatus;
use App\Enums\ReportReason;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\Review;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use App\Services\AuctionNotifier;
use App\Services\ListingSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The smaller things members do around a listing: follow it, report it, and
 * settle it with the other side once it is over.
 */
class ListingInteractionController extends Controller
{
    /**
     * Follow or unfollow a listing.
     */
    public function watch(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless($listing->isPublished(), 404);

        $listing->watchers()->toggle($request->user()->id);

        return back();
    }

    public function report(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless($listing->isPublished(), 404);

        $data = $request->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        // One report per member per listing; sending again replaces it and reopens it.
        ListingReport::query()->updateOrCreate(
            ['listing_id' => $listing->id, 'user_id' => $request->user()->id],
            [...$data, 'handled_at' => null, 'handled_by' => null],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã gửi báo cáo. Cảm ơn bạn.']);

        return back();
    }

    /**
     * The winner backs out (or the seller reports that they did). The next
     * highest bidder takes their place; with nobody left the auction is cancelled.
     */
    public function withdrawWinner(
        Listing $listing,
        AuctionEngineClient $engine,
        ListingSyncService $sync,
        AuctionNotifier $notifier,
    ): RedirectResponse {
        Gate::authorize('settle', $listing);

        $previousWinner = $listing->winner;

        try {
            $engine->withdrawWinner((int) $listing->auction_id, (int) $listing->winner_id, (int) $listing->winning_price);
        } catch (EngineException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        // Clear the old result so the refresh records whoever the engine names now.
        $listing->forceFill(['winner_id' => null, 'winning_price' => null])->save();
        $listing->reviews()->delete();
        $listing->bidders()->detach($previousWinner?->id);

        rescue(fn () => $sync->refresh($listing), report: false);

        $successor = $listing->auction_status === AuctionStatus::Completed && $listing->winner_id !== null
            ? User::query()->find($listing->winner_id)
            : null;

        $notifier->send($listing->user, MarketplaceNotification::winnerWithdrew($listing, $successor !== null));
        $notifier->send($successor, MarketplaceNotification::won($listing));

        Inertia::flash('toast', ['type' => 'success', 'message' => $successor
            ? 'Đã ghi nhận. Người đặt giá kế tiếp trở thành người thắng.'
            : 'Đã ghi nhận. Không còn người kế tiếp nên phiên đã bị huỷ.']);

        return back();
    }

    /**
     * Seller and winner rate each other, once per auction.
     */
    public function review(Request $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('settle', $listing);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $reviewer = $request->user();

        Review::query()->updateOrCreate(
            ['listing_id' => $listing->id, 'reviewer_id' => $reviewer->id],
            [
                'reviewee_id' => $reviewer->id === $listing->user_id ? $listing->winner_id : $listing->user_id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã lưu đánh giá.']);

        return back();
    }
}

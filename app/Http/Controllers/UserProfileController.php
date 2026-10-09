<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use App\Support\ListingPresenter;
use App\Support\Seo;
use Inertia\Inertia;
use Inertia\Response;

class UserProfileController extends Controller
{
    /**
     * A member's public page: what they sell and what others said about them.
     */
    public function __invoke(User $user): Response
    {
        return Inertia::render('users/Show', [
            'member' => ListingPresenter::seller($user),
            'listings' => $user->listings()
                ->published()
                ->with(['images', 'city', 'winner'])
                ->orderByRaw('auction_status in (0, 1) desc')
                ->latest('id')
                ->paginate(20)
                ->through(fn (Listing $listing) => ListingPresenter::card($listing)),
            'reviews' => array_values($user->reviewsReceived()
                ->with(['reviewer', 'listing'])
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (Review $review) => ListingPresenter::review($review))
                ->all()),
        ])->withViewData('seo', Seo::page($user->name, 'Tin đang bán và đánh giá của thành viên '.$user->name.' ('.$user->member_code.').'));
    }
}

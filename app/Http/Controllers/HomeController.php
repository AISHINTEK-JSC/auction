<?php

namespace App\Http\Controllers;

use App\Enums\AuctionStatus;
use App\Models\Listing;
use App\Support\ListingPresenter;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'running' => Listing::query()
                ->published()
                ->status(AuctionStatus::Running)
                ->with(['images', 'city'])
                ->orderBy('ends_at')
                ->paginate(20)
                ->through(fn (Listing $listing) => ListingPresenter::card($listing)),
            'upcoming' => Listing::query()
                ->published()
                ->status(AuctionStatus::Upcoming)
                ->with(['images', 'city'])
                ->orderBy('start_time')
                ->limit(10)
                ->get()
                ->map(fn (Listing $listing) => ListingPresenter::card($listing)),
        ]);
    }
}

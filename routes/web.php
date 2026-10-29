<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GiveawayController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingBidController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\ListingDraftController;
use App\Http\Controllers\ListingInteractionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\UnlockRequestController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('search', [ListingController::class, 'index'])->name('listings.index');
Route::get('c/{category:slug}', [ListingController::class, 'category'])->name('categories.show');
Route::get('t/{tag:slug}', [ListingController::class, 'tag'])->name('tags.show');
Route::get('u/{user}', UserProfileController::class)->whereNumber('user')->name('users.show');
Route::get('listings/{listing}', [ListingController::class, 'show'])->whereNumber('listing')->name('listings.show');
Route::get('listings/{listing}/bids', [ListingBidController::class, 'index'])->whereNumber('listing')->name('listings.bids.index');

Route::get('giveaways', [GiveawayController::class, 'index'])->name('giveaways.index');
Route::get('giveaways/{giveaway}', [GiveawayController::class, 'show'])->whereNumber('giveaway')->name('giveaways.show');

Route::get('terms', [SiteController::class, 'terms'])->name('legal.terms');
Route::get('privacy', [SiteController::class, 'privacy'])->name('legal.privacy');
// Fetched by crawlers: no session, no cookies.
Route::get('sitemap.xml', [SiteController::class, 'sitemap'])->withoutMiddleware('web')->name('sitemap');
Route::get('robots.txt', [SiteController::class, 'robots'])->withoutMiddleware('web')->name('robots');

// Sign in (or sign up) with Google: off when no Google client is configured.
Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('google.callback');
});

Route::get('blocked', [UnlockRequestController::class, 'create'])->name('blocked');
Route::post('unlock-requests', [UnlockRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('unlock-requests.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');

    // Posting a listing and bidding need a phone number confirmed by SMS.
    Route::middleware('phone.verified')->group(function () {
        Route::get('listings/create', [ListingController::class, 'create'])->name('listings.create');
        Route::post('listings', [ListingController::class, 'store'])->name('listings.store');
        Route::post('listings/drafts', [ListingDraftController::class, 'store'])->name('listings.drafts.store');
        Route::post('listings/{listing}/publish', [ListingDraftController::class, 'publish'])->name('listings.drafts.publish');
        Route::get('listings/{listing}/relist', [ListingController::class, 'relist'])->name('listings.relist');

        Route::post('listings/{listing}/bids', [ListingBidController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('listings.bids.store');
    });

    Route::get('listings/{listing}/draft', [ListingDraftController::class, 'edit'])->name('listings.drafts.edit');
    Route::put('listings/{listing}/draft', [ListingDraftController::class, 'update'])->name('listings.drafts.update');
    Route::delete('listings/{listing}/draft', [ListingDraftController::class, 'destroy'])->name('listings.drafts.destroy');

    Route::get('listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
    Route::put('listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
    Route::delete('listings/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');
    Route::post('listings/{listing}/sold', [ListingController::class, 'sold'])->name('listings.sold');
    Route::post('listings/{listing}/renew', [ListingController::class, 'renew'])->name('listings.renew');

    Route::delete('listings/{listing}/bids', [ListingBidController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('listings.bids.destroy');

    Route::post('listings/{listing}/watch', [ListingInteractionController::class, 'watch'])->name('listings.watch');
    Route::post('listings/{listing}/reports', [ListingInteractionController::class, 'report'])
        ->middleware('throttle:10,1')
        ->name('listings.reports.store');
    Route::post('listings/{listing}/withdraw-winner', [ListingInteractionController::class, 'withdrawWinner'])
        ->name('listings.withdraw-winner');
    Route::post('listings/{listing}/reviews', [ListingInteractionController::class, 'review'])->name('listings.reviews.store');

    Route::post('listings/{listing}/comments', [CommentController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('listings.comments.store');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('comments/{comment}/like', [CommentController::class, 'like'])->name('comments.like');

    Route::post('giveaways/{giveaway}/join', [GiveawayController::class, 'join'])->name('giveaways.join');

    Route::get('saved-searches', [SavedSearchController::class, 'index'])->name('saved-searches.index');
    Route::post('saved-searches', [SavedSearchController::class, 'store'])->name('saved-searches.store');
    Route::delete('saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])->name('saved-searches.destroy');
});

require __DIR__.'/settings.php';

// Unknown URLs go through the web middleware too, so the 404 page gets the
// session, the signed-in member and the shared header data.
Route::fallback(fn () => abort(404));

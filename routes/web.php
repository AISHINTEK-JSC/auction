<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GiveawayController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingBidController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\ListingInteractionController;
use App\Http\Controllers\NotificationController;
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

Route::get('blocked', [UnlockRequestController::class, 'create'])->name('blocked');
Route::post('unlock-requests', [UnlockRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('unlock-requests.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');

    Route::get('listings/create', [ListingController::class, 'create'])->name('listings.create');
    Route::post('listings', [ListingController::class, 'store'])->name('listings.store');
    Route::get('listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
    Route::put('listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
    Route::delete('listings/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');
    Route::get('listings/{listing}/relist', [ListingController::class, 'relist'])->name('listings.relist');
    Route::post('listings/{listing}/sold', [ListingController::class, 'sold'])->name('listings.sold');

    Route::post('listings/{listing}/bids', [ListingBidController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('listings.bids.store');
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
});

require __DIR__.'/settings.php';

// Unknown URLs go through the web middleware too, so the 404 page gets the
// session, the signed-in member and the shared header data.
Route::fallback(fn () => abort(404));

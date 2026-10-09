<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingBidController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\UnlockRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('search', [ListingController::class, 'index'])->name('listings.index');
Route::get('c/{category:slug}', [ListingController::class, 'category'])->name('categories.show');
Route::get('listings/{listing}', [ListingController::class, 'show'])->whereNumber('listing')->name('listings.show');
Route::get('listings/{listing}/bids', [ListingBidController::class, 'index'])->whereNumber('listing')->name('listings.bids.index');

Route::get('blocked', [UnlockRequestController::class, 'create'])->name('blocked');
Route::post('unlock-requests', [UnlockRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('unlock-requests.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('listings/create', [ListingController::class, 'create'])->name('listings.create');
    Route::post('listings', [ListingController::class, 'store'])->name('listings.store');
    Route::delete('listings/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');

    Route::post('listings/{listing}/bids', [ListingBidController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('listings.bids.store');

    Route::post('listings/{listing}/comments', [CommentController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('listings.comments.store');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('comments/{comment}/like', [CommentController::class, 'like'])->name('comments.like');
});

require __DIR__.'/settings.php';

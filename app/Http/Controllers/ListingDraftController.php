<?php

namespace App\Http\Controllers;

use App\Actions\Listings\CreateListing;
use App\Actions\Listings\SaveDraft;
use App\Enums\ModerationStatus;
use App\Http\Requests\Listings\PublishDraftRequest;
use App\Http\Requests\Listings\SaveDraftRequest;
use App\Models\Listing;
use App\Services\AuctionEngine\EngineException;
use App\Services\ListingSyncService;
use App\Services\SavedSearchMatcher;
use App\Support\ListingForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Drafts: a listing its seller saves to finish later. Nobody else sees it and
 * nothing reaches the auction engine until it is published.
 */
class ListingDraftController extends Controller
{
    private const MAX_PER_MEMBER = 20;

    public function store(SaveDraftRequest $request, SaveDraft $saveDraft): RedirectResponse
    {
        $drafts = $request->user()->listings()->where('moderation_status', ModerationStatus::Draft)->count();

        if ($drafts >= self::MAX_PER_MEMBER) {
            throw ValidationException::withMessages([
                'title' => 'Bạn đang có '.self::MAX_PER_MEMBER.' tin nháp. Hãy đăng hoặc xoá bớt trước khi lưu thêm.',
            ]);
        }

        $draft = $saveDraft->handle(
            $request->user(),
            $request->safe()->except('images'),
            array_values(Arr::wrap($request->file('images'))),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã lưu nháp. Bạn có thể sửa tiếp bất cứ lúc nào.']);

        return to_route('listings.drafts.edit', $draft);
    }

    /**
     * The create form, filled with what was saved.
     */
    public function edit(Listing $listing): Response
    {
        Gate::authorize('manageDraft', $listing);

        $listing->load(['images', 'tags']);

        return Inertia::render('listings/Create', ListingForm::createOptions() + [
            'source' => null,
            'draft' => [
                ...ListingForm::editable($listing),
                ...ListingForm::sale($listing),
                // datetime-local format; a start that has already passed is not offered again.
                'start_time' => $listing->start_time?->isFuture() ? $listing->start_time->format('Y-m-d\TH:i') : '',
                'end_time' => $listing->end_time?->isFuture() ? $listing->end_time->format('Y-m-d\TH:i') : '',
                'updated_at' => $listing->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(SaveDraftRequest $request, Listing $listing, SaveDraft $saveDraft): RedirectResponse
    {
        $saveDraft->handle(
            $request->user(),
            $request->safe()->except('images'),
            array_values(Arr::wrap($request->file('images'))),
            $listing,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã lưu nháp.']);

        return to_route('listings.drafts.edit', $listing);
    }

    /**
     * Post the draft for real, with the full checks of a new listing.
     */
    public function publish(
        PublishDraftRequest $request,
        Listing $listing,
        CreateListing $createListing,
        ListingSyncService $sync,
        SavedSearchMatcher $savedSearches,
    ): RedirectResponse {
        try {
            $createListing->handle(
                $request->user(),
                $request->safe()->except(['images', 'relist_from']),
                array_values(Arr::wrap($request->file('images'))),
                draft: $listing,
            );
        } catch (EngineException $e) {
            throw ValidationException::withMessages(
                $e->fieldErrors === [] ? ['engine' => $e->getMessage()] : array_map(fn (array $messages) => (string) ($messages[0] ?? ''), $e->fieldErrors),
            );
        }

        if (! $listing->isPublished()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã gửi tin. Tin sẽ hiển thị sau khi được duyệt.']);

            return to_route('dashboard');
        }

        if ($listing->isAuction()) {
            rescue(fn () => $sync->refresh($listing), report: false);
        }

        rescue(fn () => $savedSearches->listingPublished($listing));

        Inertia::flash('toast', ['type' => 'success', 'message' => $listing->isAuction() ? 'Đã đăng tin đấu giá.' : 'Đã đăng tin.']);

        return to_route('listings.show', $listing);
    }

    /**
     * Throw the draft away, files included.
     */
    public function destroy(Request $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('manageDraft', $listing);

        $paths = $listing->images()->pluck('path')->all();
        $listing->delete();
        Storage::disk('public')->delete($paths);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã xoá tin nháp.']);

        return to_route('dashboard');
    }
}

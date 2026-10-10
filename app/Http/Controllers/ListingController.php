<?php

namespace App\Http\Controllers;

use App\Actions\Listings\CreateListing;
use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
use App\Enums\ModerationStatus;
use App\Enums\ReportReason;
use App\Enums\SaleType;
use App\Http\Requests\Listings\StoreListingRequest;
use App\Http\Requests\Listings\UpdateListingRequest;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Listing;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use App\Services\ListingImages;
use App\Services\ListingSyncService;
use App\Services\SavedSearchMatcher;
use App\Support\BidPresenter;
use App\Support\Brand;
use App\Support\HtmlCleaner;
use App\Support\ListingForm;
use App\Support\ListingPresenter;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    /**
     * Engine field names that differ from the names of our form fields.
     */
    private const ENGINE_FIELDS = [
        'auto_extend_bid_time' => 'auto_extend',
        'down_time' => 'down_minutes',
    ];

    private const TYPE_FIXED = 3;

    public function index(Request $request): Response
    {
        return $this->browse($request);
    }

    public function category(Request $request, Category $category): Response
    {
        return $this->browse($request, category: $category);
    }

    public function tag(Request $request, Tag $tag): Response
    {
        return $this->browse($request, tag: $tag);
    }

    public function show(Request $request, Listing $listing, ListingSyncService $sync): Response
    {
        $viewer = $request->user();

        abort_unless(Gate::forUser($viewer)->allows('view', $listing), 404);

        $listing->load(['images', 'city', 'category', 'tags', 'user', 'winner']);

        $this->countView($request, $listing);

        // "auction" and "bids" are the two props the page polls; everything else is
        // wrapped in closures so a poll does not recompute it.
        return Inertia::render('listings/Show', [
            // A fixed-price listing has no auction: nothing to ask the engine.
            'auction' => fn () => $listing->isAuction() ? $sync->liveState($listing) : null,
            'bids' => fn () => $listing->isAuction() ? BidPresenter::present(
                array_slice($sync->liveBids($listing), 0, 5),
                $viewer,
                $listing->auction_status === AuctionStatus::Running,
            ) : [],
            // liveState() may just have recorded the winner on this same instance.
            'listing' => fn () => ListingPresenter::detail($listing->unsetRelation('winner'), $viewer),
            'comments' => fn () => $this->comments($listing, $viewer),
            'reportReasons' => fn () => ReportReason::options(),
            'sameSeller' => fn () => $this->cards(
                Listing::query()->where('user_id', $listing->user_id),
                $listing,
                4,
            ),
            'related' => fn () => $this->cards(
                Listing::query()->where('category_id', $listing->category_id),
                $listing,
                8,
            ),
        ])->withViewData('seo', Seo::listing($listing));
    }

    public function create(): Response
    {
        return Inertia::render('listings/Create', ListingForm::createOptions() + ['source' => null, 'draft' => null]);
    }

    /**
     * The create form, prefilled from a listing whose auction ended without a
     * winner (or that a moderator rejected). Posting it makes a new listing.
     */
    public function relist(Listing $listing): Response
    {
        Gate::authorize('relist', $listing);

        $listing->load(['images', 'tags']);

        return Inertia::render('listings/Create', ListingForm::createOptions() + [
            'source' => [...ListingForm::editable($listing), ...ListingForm::sale($listing)],
            'draft' => null,
        ]);
    }

    public function store(
        StoreListingRequest $request,
        CreateListing $createListing,
        ListingSyncService $sync,
        SavedSearchMatcher $savedSearches,
    ): RedirectResponse {
        try {
            $listing = $createListing->handle(
                $request->user(),
                $request->safe()->except(['images', 'relist_from']),
                array_values(Arr::wrap($request->file('images'))),
                $request->source(),
            );
        } catch (EngineException $e) {
            throw ValidationException::withMessages($this->engineErrors($e));
        }

        if (! $listing->isPublished()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã gửi tin. Tin sẽ hiển thị sau khi được duyệt.']);

            return to_route('dashboard');
        }

        if ($listing->isAuction()) {
            // Best effort: the scheduler syncs it within a minute anyway.
            rescue(fn () => $sync->refresh($listing), report: false);
        }

        rescue(fn () => $savedSearches->listingPublished($listing));

        Inertia::flash('toast', ['type' => 'success', 'message' => $listing->isAuction() ? 'Đã đăng tin đấu giá.' : 'Đã đăng tin.']);

        return to_route('listings.show', $listing);
    }

    public function edit(Listing $listing): Response|RedirectResponse
    {
        // A draft is edited in the full create form, where it can also be published.
        if ($listing->isDraft()) {
            return to_route('listings.drafts.edit', $listing);
        }

        Gate::authorize('update', $listing);

        $listing->load(['images', 'tags']);

        return Inertia::render('listings/Edit', ListingForm::options() + [
            'listing' => ListingForm::editable($listing) + ['has_auction' => $listing->isLive()],
        ]);
    }

    public function update(UpdateListingRequest $request, Listing $listing, ListingImages $images): RedirectResponse
    {
        Gate::authorize('update', $listing);

        $data = $request->validated();

        $changes = DB::transaction(function () use ($request, $listing, $data, $images): array {
            $listing->fill([
                'title' => $data['title'],
                'description' => HtmlCleaner::clean($data['description'] ?? null),
                'category_id' => $data['category_id'],
                'city_id' => $data['city_id'],
                'condition' => $data['condition'],
                'brand' => Brand::normalize($data['brand'] ?? null),
            ]);

            // A rejected listing goes back to the moderators once its seller has fixed it.
            if ($listing->moderation_status === ModerationStatus::Rejected) {
                $listing->moderation_status = ModerationStatus::Pending;
                $listing->moderation_note = null;
            }

            $listing->save();
            $listing->tags()->sync(CreateListing::tagIds($data['tags'] ?? []));

            return $images->sync(
                $listing,
                $data['remove_images'] ?? [],
                $data['image_order'] ?? [],
                array_values(Arr::wrap($request->file('images'))),
            );
        });

        Storage::disk('public')->delete($changes['removed']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã lưu tin đăng.']);

        return Gate::allows('view', $listing)
            ? to_route('listings.show', $listing)
            : to_route('dashboard');
    }

    /**
     * The seller of a fixed-price listing marks the item as sold.
     */
    public function sold(Listing $listing): RedirectResponse
    {
        Gate::authorize('markSold', $listing);

        $listing->forceFill(['auction_status' => AuctionStatus::Completed, 'ends_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã đánh dấu là đã bán.']);

        return back();
    }

    /**
     * The seller gives a fixed-price listing a fresh lifetime, also after it expired.
     */
    public function renew(Listing $listing): RedirectResponse
    {
        Gate::authorize('renew', $listing);

        $listing->forceFill([
            'auction_status' => AuctionStatus::Running,
            'ends_at' => CreateListing::fixedListingDeadline(),
            'expiry_reminded_at' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã gia hạn tới '.$listing->ends_at->format('d/m/Y').'.']);

        return back();
    }

    /**
     * The seller cancels the auction, or takes a fixed-price listing down. The
     * listing itself is kept as history.
     */
    public function destroy(Listing $listing, AuctionEngineClient $engine, ListingSyncService $sync): RedirectResponse
    {
        Gate::authorize('cancel', $listing);

        if ($listing->isFixedPrice()) {
            $listing->forceFill(['auction_status' => AuctionStatus::CancelledBySeller, 'ends_at' => now()])->save();

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã gỡ tin.']);

            return back();
        }

        try {
            $engine->cancelAuction((int) $listing->auction_id);
            $sync->refresh($listing);
        } catch (EngineException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã huỷ phiên đấu giá.']);

        return back();
    }

    /**
     * Count a visit once per browser session: not the seller's own, and not
     * the partial reloads the page makes while polling.
     */
    private function countView(Request $request, Listing $listing): void
    {
        if ($request->hasHeader('X-Inertia-Partial-Data') || $request->user()?->id === $listing->user_id) {
            return;
        }

        $seen = (array) $request->session()->get('viewed_listings', []);

        if (in_array($listing->id, $seen, true)) {
            return;
        }

        // Without touching updated_at: a view is not an edit.
        Listing::withoutTimestamps(fn () => $listing->increment('view_count'));

        $request->session()->put('viewed_listings', array_slice([...$seen, $listing->id], -200));
    }

    private function browse(Request $request, ?Category $category = null, ?Tag $tag = null): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'city_id' => ['nullable', 'integer'],
            // 1 and 2 are the auction types; 3 stands for fixed-price listings.
            'type' => ['nullable', 'integer', 'in:1,2,3'],
            'status' => ['nullable', 'in:open,running,upcoming,ended'],
            'sort' => ['nullable', 'in:ending,newest,popular,price_asc,price_desc'],
        ]);

        $status = $filters['status'] ?? 'open';
        $sort = $filters['sort'] ?? ($status === 'ended' ? 'newest' : 'ending');

        $query = Listing::query()
            ->published()
            ->with(['images', 'city', 'winner'])
            ->when($category, fn (Builder $q) => $q->whereIn('category_id', $category->selfAndChildIds()))
            ->when($tag, fn (Builder $q) => $q->whereRelation('tags', 'tags.id', $tag->id))
            // The word may be in the title or be the brand.
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $either) => $either
                ->whereLike('title', $pattern = '%'.addcslashes($term, '%_\\').'%')
                ->orWhereLike('brand', $pattern)))
            ->when($filters['city_id'] ?? null, fn (Builder $q, int|string $cityId) => $q->where('city_id', $cityId))
            ->when($filters['type'] ?? null, fn (Builder $q, int|string $type) => (int) $type === self::TYPE_FIXED
                ? $q->where('sale_type', SaleType::Fixed)
                : $q->where('auction_type', AuctionType::from((int) $type)));

        match ($status) {
            'running' => $query->status(AuctionStatus::Running),
            'upcoming' => $query->status(AuctionStatus::Upcoming),
            'ended' => $query->whereNotIn('auction_status', AuctionStatus::openValues()),
            default => $query->whereIn('auction_status', AuctionStatus::openValues()),
        };

        match ($sort) {
            'newest' => $query->latest('id'),
            'popular' => $query->orderByDesc('view_count')->orderByDesc('bid_count')->latest('id'),
            // Listings without a stated price go last either way.
            'price_asc' => $query->orderBy('is_negotiable')->orderBy('current_price'),
            'price_desc' => $query->orderBy('is_negotiable')->orderByDesc('current_price'),
            default => $query->orderByRaw('ends_at is null')->orderBy('ends_at'),
        };

        return Inertia::render('listings/Index', [
            'listings' => $query
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Listing $listing) => ListingPresenter::card($listing)),
            'category' => $category ? ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug] : null,
            'tag' => $tag ? ['name' => $tag->name, 'slug' => $tag->slug] : null,
            'savedSearch' => $this->savedSearchState($request, $filters, $category, $tag),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'city_id' => isset($filters['city_id']) ? (int) $filters['city_id'] : null,
                'type' => isset($filters['type']) ? (int) $filters['type'] : null,
                'status' => $status,
                'sort' => $sort,
            ],
        ])->withViewData('seo', match (true) {
            $category !== null => Seo::page($category->name, 'Tin đấu giá và mua bán trong danh mục '.$category->name.': xem giá hiện tại, thời gian còn lại và đặt giá trực tuyến.'),
            $tag !== null => Seo::page('Thẻ '.$tag->name, 'Các tin đăng gắn thẻ "'.$tag->name.'".'),
            // A search for a word is one visitor's page, not something to list in a search engine.
            default => Seo::page('Tất cả tin đăng', 'Tìm tin đấu giá và tin giá cố định theo từ khoá, tỉnh thành, loại tin và trạng thái.', indexable: blank($filters['q'] ?? null)),
        });
    }

    /**
     * Whether the search on screen can be saved, and whether the member
     * already saved it. Tag pages cannot be saved: a tag is not a criterion.
     *
     * @param  array<string, mixed>  $filters
     * @return array{criteria: array<string, mixed>, can_save: bool, id: int|null}|null
     */
    private function savedSearchState(Request $request, array $filters, ?Category $category, ?Tag $tag): ?array
    {
        if ($request->user() === null || $tag !== null) {
            return null;
        }

        $criteria = [
            'q' => filled($filters['q'] ?? null) ? trim((string) $filters['q']) : null,
            'category_id' => $category?->id,
            'city_id' => isset($filters['city_id']) ? (int) $filters['city_id'] : null,
            'type' => isset($filters['type']) ? (int) $filters['type'] : null,
        ];

        return [
            'criteria' => $criteria,
            'can_save' => array_filter($criteria, fn ($value) => $value !== null) !== [],
            'id' => $request->user()->savedSearches()->where($criteria)->value('id'),
        ];
    }

    /**
     * @param  Builder<Listing>  $query
     * @return list<array<string, mixed>>
     */
    private function cards(Builder $query, Listing $except, int $limit): array
    {
        return array_values($query
            ->published()
            ->whereKeyNot($except->id)
            ->whereIn('auction_status', AuctionStatus::openValues())
            ->with(['images', 'city'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Listing $listing) => ListingPresenter::card($listing))
            ->all());
    }

    /**
     * Top-level comments, newest first, each with its replies.
     *
     * @return list<array<string, mixed>>
     */
    private function comments(Listing $listing, ?User $viewer): array
    {
        $present = fn (Comment $comment): array => [
            'id' => $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toIso8601String(),
            'user' => ['id' => $comment->user->id, 'name' => $comment->user->name, 'avatar' => $comment->user->avatar],
            'likes' => (int) $comment->likers_count,
            'liked' => (bool) ($comment->liked ?? false),
            'can_delete' => $viewer !== null && $viewer->can('delete', $comment),
        ];

        $decorate = fn ($query) => $query
            ->with('user')
            ->withCount('likers')
            ->when($viewer, fn ($q) => $q->withExists(['likers as liked' => fn ($likers) => $likers->whereKey($viewer->id)]));

        return array_values($listing->comments()
            ->whereNull('parent_id')
            ->tap($decorate)
            ->with(['replies' => $decorate])
            ->latest()
            ->get()
            ->map(fn (Comment $comment) => [
                ...$present($comment),
                'replies' => array_values($comment->replies->map($present)->all()),
            ])
            ->all());
    }

    /**
     * Turn the engine's validation map into errors keyed by our form fields.
     *
     * @return array<string, string>
     */
    private function engineErrors(EngineException $e): array
    {
        if ($e->fieldErrors === []) {
            return ['engine' => $e->getMessage()];
        }

        $errors = [];

        foreach ($e->fieldErrors as $field => $messages) {
            $errors[self::ENGINE_FIELDS[$field] ?? $field] = (string) ($messages[0] ?? $e->getMessage());
        }

        return $errors;
    }
}

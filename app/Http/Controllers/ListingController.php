<?php

namespace App\Http\Controllers;

use App\Actions\Listings\CreateListing;
use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
use App\Enums\ItemCondition;
use App\Enums\ModerationStatus;
use App\Enums\ReportReason;
use App\Http\Requests\Listings\StoreListingRequest;
use App\Http\Requests\Listings\UpdateListingRequest;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Listing;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use App\Services\ListingSyncService;
use App\Support\BidPresenter;
use App\Support\HtmlCleaner;
use App\Support\ListingPresenter;
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

        // "auction" and "bids" are the two props the page polls; everything else is
        // wrapped in closures so a poll does not recompute it.
        return Inertia::render('listings/Show', [
            'auction' => fn () => $sync->liveState($listing),
            'bids' => fn () => BidPresenter::present(
                array_slice($sync->liveBids($listing), 0, 5),
                $viewer,
                $listing->auction_status === AuctionStatus::Running,
            ),
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
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('listings/Create', $this->formOptions() + ['source' => null]);
    }

    /**
     * The create form, prefilled from a listing whose auction ended without a
     * winner (or that a moderator rejected). Posting it makes a new listing.
     */
    public function relist(Listing $listing): Response
    {
        Gate::authorize('relist', $listing);

        $listing->load(['images', 'tags']);

        return Inertia::render('listings/Create', $this->formOptions() + [
            'source' => [
                ...$this->editable($listing),
                'auction_type' => $listing->auction_type->value,
                'start_price' => $listing->start_price,
                'min_bid_price' => $listing->min_bid_price,
                'auto_extend' => $listing->auto_extend,
                'down_minutes' => $listing->down_time ? intdiv($listing->down_time, 60) : null,
                'down_price' => $listing->down_price,
                'finish_price' => $listing->finish_price,
            ],
        ]);
    }

    public function store(StoreListingRequest $request, CreateListing $createListing, ListingSyncService $sync): RedirectResponse
    {
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

        if ($listing->auction_id === null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã gửi tin. Tin sẽ hiển thị sau khi được duyệt.']);

            return to_route('dashboard');
        }

        // Best effort: the scheduler syncs it within a minute anyway.
        rescue(fn () => $sync->refresh($listing), report: false);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã đăng tin đấu giá.']);

        return to_route('listings.show', $listing);
    }

    public function edit(Listing $listing): Response
    {
        Gate::authorize('update', $listing);

        $listing->load(['images', 'tags']);

        return Inertia::render('listings/Edit', $this->formOptions() + [
            'listing' => $this->editable($listing) + ['has_auction' => $listing->auction_id !== null],
        ]);
    }

    public function update(UpdateListingRequest $request, Listing $listing): RedirectResponse
    {
        Gate::authorize('update', $listing);

        $data = $request->validated();
        $removed = $listing->images()->whereIn('id', $data['remove_images'] ?? [])->get();

        DB::transaction(function () use ($request, $listing, $data, $removed): void {
            $listing->fill([
                'title' => $data['title'],
                'description' => HtmlCleaner::clean($data['description'] ?? null),
                'category_id' => $data['category_id'],
                'city_id' => $data['city_id'],
                'condition' => $data['condition'],
            ]);

            // A rejected listing goes back to the moderators once its seller has fixed it.
            if ($listing->moderation_status === ModerationStatus::Rejected) {
                $listing->moderation_status = ModerationStatus::Pending;
                $listing->moderation_note = null;
            }

            $listing->save();
            $listing->tags()->sync(CreateListing::tagIds($data['tags'] ?? []));

            $listing->images()->whereKey($removed->modelKeys())->delete();
            $position = (int) $listing->images()->max('position');

            foreach (Arr::wrap($request->file('images')) as $image) {
                $listing->images()->create([
                    'path' => $image->store("listings/{$listing->id}", 'public'),
                    'position' => ++$position,
                ]);
            }
        });

        Storage::disk('public')->delete($removed->pluck('path')->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã lưu tin đăng.']);

        return $listing->auction_id !== null && Gate::allows('view', $listing)
            ? to_route('listings.show', $listing)
            : to_route('dashboard');
    }

    /**
     * The seller cancels the auction. The listing itself is kept as history.
     */
    public function destroy(Listing $listing, AuctionEngineClient $engine, ListingSyncService $sync): RedirectResponse
    {
        Gate::authorize('cancel', $listing);

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

    private function browse(Request $request, ?Category $category = null, ?Tag $tag = null): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'city_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'integer', 'in:1,2'],
            'status' => ['nullable', 'in:open,running,upcoming,ended'],
            'sort' => ['nullable', 'in:ending,newest,price_asc,price_desc'],
        ]);

        $status = $filters['status'] ?? 'open';
        $sort = $filters['sort'] ?? ($status === 'ended' ? 'newest' : 'ending');

        $query = Listing::query()
            ->published()
            ->with(['images', 'city', 'winner'])
            ->when($category, fn (Builder $q) => $q->whereIn('category_id', $category->selfAndChildIds()))
            ->when($tag, fn (Builder $q) => $q->whereRelation('tags', 'tags.id', $tag->id))
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->whereLike('title', '%'.addcslashes($term, '%_\\').'%'))
            ->when($filters['city_id'] ?? null, fn (Builder $q, int|string $cityId) => $q->where('city_id', $cityId))
            ->when($filters['type'] ?? null, fn (Builder $q, int|string $type) => $q->where('auction_type', AuctionType::from((int) $type)));

        match ($status) {
            'running' => $query->status(AuctionStatus::Running),
            'upcoming' => $query->status(AuctionStatus::Upcoming),
            'ended' => $query->whereNotIn('auction_status', AuctionStatus::openValues()),
            default => $query->whereIn('auction_status', AuctionStatus::openValues()),
        };

        match ($sort) {
            'newest' => $query->latest('id'),
            'price_asc' => $query->orderBy('current_price'),
            'price_desc' => $query->orderByDesc('current_price'),
            default => $query->orderByRaw('ends_at is null')->orderBy('ends_at'),
        };

        return Inertia::render('listings/Index', [
            'listings' => $query
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Listing $listing) => ListingPresenter::card($listing)),
            'category' => $category ? ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug] : null,
            'tag' => $tag ? ['name' => $tag->name, 'slug' => $tag->slug] : null,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'city_id' => isset($filters['city_id']) ? (int) $filters['city_id'] : null,
                'type' => isset($filters['type']) ? (int) $filters['type'] : null,
                'status' => $status,
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * @return array{conditions: list<array{value: string, label: string}>}
     */
    private function formOptions(): array
    {
        return [
            'conditions' => array_map(
                fn (ItemCondition $condition) => ['value' => $condition->value, 'label' => $condition->label()],
                ItemCondition::cases(),
            ),
        ];
    }

    /**
     * The fields of a listing its seller can change. Expects images and tags to be loaded.
     *
     * @return array<string, mixed>
     */
    private function editable(Listing $listing): array
    {
        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'description' => $listing->description ?? '',
            'category_id' => $listing->category_id,
            'city_id' => $listing->city_id,
            'condition' => $listing->condition->value,
            'tags' => $listing->tags->pluck('name')->all(),
            'images' => $listing->images->map(fn ($image) => ['id' => $image->id, 'url' => $image->url])->all(),
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

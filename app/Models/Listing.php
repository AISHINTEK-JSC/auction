<?php

namespace App\Models;

use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
use App\Enums\ItemCondition;
use App\Enums\ModerationStatus;
use App\Enums\SaleType;
use Carbon\CarbonInterface;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A marketplace post. Prices, bids and timing live in the auction engine; the
 * auction_status / current_price / bid_count / ends_at / winner columns are a
 * snapshot kept fresh by App\Services\ListingSyncService.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $category_id
 * @property int|null $city_id
 * @property string $title
 * @property string|null $description
 * @property ItemCondition $condition
 * @property SaleType $sale_type
 * @property ModerationStatus $moderation_status
 * @property string|null $moderation_note
 * @property int|null $relisted_from_id
 * @property int|null $auction_id
 * @property AuctionType|null $auction_type null for fixed-price listings
 * @property int $start_price
 * @property bool $is_negotiable "giá thoả thuận": a fixed-price listing without a stated price
 * @property int|null $min_bid_price
 * @property int|null $bid_step
 * @property CarbonInterface|null $start_time
 * @property CarbonInterface|null $end_time
 * @property bool $auto_extend
 * @property int|null $down_time
 * @property int|null $down_price
 * @property int|null $finish_price
 * @property AuctionStatus $auction_status
 * @property int $current_price
 * @property int $bid_count
 * @property int $view_count
 * @property int|null $leader_id
 * @property CarbonInterface|null $ends_at
 * @property int|null $winner_id
 * @property int|null $winning_price
 * @property CarbonInterface|null $synced_at
 * @property CarbonInterface|null $closed_notified_at
 * @property CarbonInterface|null $expiry_reminded_at
 * @property CarbonInterface|null $created_at
 */
#[Fillable([
    'user_id', 'category_id', 'city_id', 'title', 'description', 'condition', 'sale_type',
    'moderation_status', 'moderation_note', 'relisted_from_id', 'leader_id', 'closed_notified_at',
    'auction_id', 'auction_type', 'start_price', 'is_negotiable', 'min_bid_price', 'bid_step', 'start_time', 'end_time',
    'auto_extend', 'down_time', 'down_price', 'finish_price',
    'auction_status', 'current_price', 'bid_count', 'ends_at', 'winner_id', 'winning_price', 'synced_at',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    /**
     * Same as the column defaults, so a freshly created model knows its sale
     * type and moderation status without being re-read from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sale_type' => 'auction',
        'moderation_status' => 'approved',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition' => ItemCondition::class,
            'sale_type' => SaleType::class,
            'moderation_status' => ModerationStatus::class,
            'closed_notified_at' => 'datetime',
            'expiry_reminded_at' => 'datetime',
            'auction_type' => AuctionType::class,
            'auction_status' => AuctionStatus::class,
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'ends_at' => 'datetime',
            'synced_at' => 'datetime',
            'auto_extend' => 'boolean',
            'is_negotiable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return HasMany<ListingImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'listing_watchers');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function bidders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'listing_bidders')->withPivot('last_bid_price')->withTimestamps();
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<ListingReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ListingReport::class);
    }

    /**
     * Still being written by its seller: only they can see it, and only in the
     * draft form.
     */
    public function isDraft(): bool
    {
        return $this->moderation_status === ModerationStatus::Draft;
    }

    public function isAuction(): bool
    {
        return $this->sale_type === SaleType::Auction;
    }

    public function isFixedPrice(): bool
    {
        return $this->sale_type === SaleType::Fixed;
    }

    /**
     * Whether the listing can be shown at all. An auction listing needs its
     * auction in the engine: without one it is waiting for approval, or is the
     * leftover of a failed create. A fixed-price listing needs nothing else.
     */
    public function isLive(): bool
    {
        return $this->isFixedPrice() || $this->auction_id !== null;
    }

    /**
     * Visible to everyone: live, and approved by moderation.
     */
    public function isPublished(): bool
    {
        return $this->isLive() && $this->moderation_status === ModerationStatus::Approved;
    }

    /**
     * Fixed-price listings reuse the auction statuses: Running is "for sale",
     * Completed is "sold", CancelledBySeller is "taken down", Expired is
     * "ran out of time" (see the listings:expire command).
     */
    public function statusLabel(): string
    {
        if ($this->isAuction()) {
            return $this->auction_status->label();
        }

        return match ($this->auction_status) {
            AuctionStatus::Completed => 'Đã bán',
            AuctionStatus::Running, AuctionStatus::Upcoming => 'Đang bán',
            AuctionStatus::Expired => 'Hết hạn',
            default => 'Đã gỡ',
        };
    }

    /**
     * @param  Builder<Listing>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query
            ->where('moderation_status', ModerationStatus::Approved)
            ->where(fn (Builder $live) => $live
                ->where('sale_type', SaleType::Fixed)
                ->orWhereNotNull('auction_id'));
    }

    /**
     * @param  Builder<Listing>  $query
     */
    public function scopeStatus(Builder $query, AuctionStatus ...$statuses): void
    {
        $query->whereIn('auction_status', array_map(fn (AuctionStatus $status) => $status->value, $statuses));
    }
}

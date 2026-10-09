<?php

namespace App\Models;

use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
use App\Enums\ItemCondition;
use App\Enums\ModerationStatus;
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
 * @property ModerationStatus $moderation_status
 * @property string|null $moderation_note
 * @property int|null $relisted_from_id
 * @property int|null $auction_id
 * @property AuctionType $auction_type
 * @property int $start_price
 * @property int|null $min_bid_price
 * @property CarbonInterface|null $start_time
 * @property CarbonInterface|null $end_time
 * @property bool $auto_extend
 * @property int|null $down_time
 * @property int|null $down_price
 * @property int|null $finish_price
 * @property AuctionStatus $auction_status
 * @property int $current_price
 * @property int $bid_count
 * @property int|null $leader_id
 * @property CarbonInterface|null $ends_at
 * @property int|null $winner_id
 * @property int|null $winning_price
 * @property CarbonInterface|null $synced_at
 * @property CarbonInterface|null $closed_notified_at
 * @property CarbonInterface|null $created_at
 */
#[Fillable([
    'user_id', 'category_id', 'city_id', 'title', 'description', 'condition',
    'moderation_status', 'moderation_note', 'relisted_from_id', 'leader_id', 'closed_notified_at',
    'auction_id', 'auction_type', 'start_price', 'min_bid_price', 'start_time', 'end_time',
    'auto_extend', 'down_time', 'down_price', 'finish_price',
    'auction_status', 'current_price', 'bid_count', 'ends_at', 'winner_id', 'winning_price', 'synced_at',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition' => ItemCondition::class,
            'moderation_status' => ModerationStatus::class,
            'closed_notified_at' => 'datetime',
            'auction_type' => AuctionType::class,
            'auction_status' => AuctionStatus::class,
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'ends_at' => 'datetime',
            'synced_at' => 'datetime',
            'auto_extend' => 'boolean',
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
     * Visible to everyone: approved by moderation and live in the engine. A
     * listing without an auction_id is either waiting for approval or the
     * leftover of a failed create.
     */
    public function isPublished(): bool
    {
        return $this->auction_id !== null && $this->moderation_status === ModerationStatus::Approved;
    }

    /**
     * @param  Builder<Listing>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('auction_id')->where('moderation_status', ModerationStatus::Approved);
    }

    /**
     * @param  Builder<Listing>  $query
     */
    public function scopeStatus(Builder $query, AuctionStatus ...$statuses): void
    {
        $query->whereIn('auction_status', array_map(fn (AuctionStatus $status) => $status->value, $statuses));
    }
}

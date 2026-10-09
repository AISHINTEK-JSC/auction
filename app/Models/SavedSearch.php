<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A search a member asked to be told about. Every criterion is optional; a
 * new listing matches when it meets all that are set.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $q
 * @property int|null $category_id
 * @property int|null $city_id
 * @property int|null $type 1 ascending auction, 2 descending auction, 3 fixed price
 */
#[Fillable(['user_id', 'q', 'category_id', 'city_id', 'type'])]
class SavedSearch extends Model
{
    public const TYPE_LABELS = [1 => 'Đấu giá xuôi', 2 => 'Đấu giá ngược', 3 => 'Giá cố định'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * What the search is for, in words: «"xe đạp" · Xe cộ · Hà Nội».
     */
    public function label(): string
    {
        return implode(' · ', array_filter([
            $this->q !== null ? '"'.$this->q.'"' : null,
            $this->category?->name,
            $this->city?->name,
            $this->type !== null ? (self::TYPE_LABELS[$this->type] ?? null) : null,
        ])) ?: 'Tất cả tin đăng';
    }

    /**
     * Where running the search again leads.
     */
    public function url(): string
    {
        $query = array_filter(['q' => $this->q, 'city_id' => $this->city_id, 'type' => $this->type]);

        return $this->category !== null
            ? route('categories.show', [$this->category, ...$query], absolute: false)
            : route('listings.index', $query, absolute: false);
    }
}

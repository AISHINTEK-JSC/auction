<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property int $position
 */
#[Fillable(['parent_id', 'name', 'slug', 'position'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const TREE_CACHE_KEY = 'categories.tree';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::TREE_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::TREE_CACHE_KEY));
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * Ids of this category and its direct children, for "listings in this category".
     *
     * @return list<int>
     */
    public function selfAndChildIds(): array
    {
        return [$this->id, ...$this->children->map(fn (self $child) => $child->id)->all()];
    }

    /**
     * Two-level tree for the header menu.
     *
     * @return list<array{id: int, name: string, slug: string, children: list<array{id: int, name: string, slug: string}>}>
     */
    public static function tree(): array
    {
        return Cache::remember(self::TREE_CACHE_KEY, now()->addMinutes(10), fn () => array_values(self::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (self $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'children' => array_values($category->children
                    ->map(fn (self $child) => ['id' => $child->id, 'name' => $child->name, 'slug' => $child->slug])
                    ->all()),
            ])
            ->all()));
    }
}

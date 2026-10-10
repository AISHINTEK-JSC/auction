<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One photo of a listing, stored in two sizes (see App\Support\ImageOptimizer).
 *
 * @property int $id
 * @property int $listing_id
 * @property string $path
 * @property string|null $thumb_path
 * @property int $position
 * @property-read string $url the large version: listing page, zoom, link previews
 * @property-read string $thumb_url the small version: cards and thumbnail strips
 */
#[Fillable(['listing_id', 'path', 'thumb_path', 'position'])]
class ListingImage extends Model
{
    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * Every file this image owns on the public disk.
     *
     * @return list<string>
     */
    public function files(): array
    {
        return array_values(array_filter([$this->path, $this->thumb_path]));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk('public')->url($this->path));
    }

    /**
     * An image without a thumbnail (its upload could not be processed) shows its large version.
     *
     * @return Attribute<string, never>
     */
    protected function thumbUrl(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk('public')->url($this->thumb_path ?? $this->path));
    }
}

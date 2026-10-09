<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A prize an admin gives away. Members sign up once each; winners are drawn at random.
 *
 * @property int $id
 * @property string $title
 * @property string $prize
 * @property string|null $description
 * @property string|null $image_path
 * @property int $winner_count
 * @property CarbonInterface $ends_at
 * @property CarbonInterface|null $drawn_at
 * @property-read string|null $image
 */
#[Fillable(['title', 'prize', 'description', 'image_path', 'winner_count', 'ends_at'])]
class Giveaway extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'drawn_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<GiveawayEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(GiveawayEntry::class);
    }

    /**
     * @return HasMany<GiveawayEntry, $this>
     */
    public function winners(): HasMany
    {
        return $this->entries()->where('is_winner', true);
    }

    public function isDrawn(): bool
    {
        return $this->drawn_at !== null;
    }

    /**
     * Members can still sign up: not past the deadline and not drawn yet.
     */
    public function isOpen(): bool
    {
        return ! $this->isDrawn() && $this->ends_at->isFuture();
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function image(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $listing_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $body
 * @property Carbon|null $created_at
 */
#[Fillable(['listing_id', 'user_id', 'parent_id', 'body'])]
class Comment extends Model
{
    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comment_likes');
    }
}

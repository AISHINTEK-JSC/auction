<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Feedback one side of a finished auction leaves for the other.
 *
 * @property int $id
 * @property int $listing_id
 * @property int $reviewer_id
 * @property int $reviewee_id
 * @property int $rating
 * @property string|null $comment
 * @property Carbon|null $created_at
 */
#[Fillable(['listing_id', 'reviewer_id', 'reviewee_id', 'rating', 'comment'])]
class Review extends Model
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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}

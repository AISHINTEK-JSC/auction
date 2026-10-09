<?php

namespace App\Models;

use App\Enums\ReportReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $listing_id
 * @property int $user_id
 * @property ReportReason $reason
 * @property string|null $note
 * @property Carbon|null $handled_at
 * @property int|null $handled_by
 */
#[Fillable(['listing_id', 'user_id', 'reason', 'note', 'handled_at', 'handled_by'])]
class ListingReport extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'handled_at' => 'datetime',
        ];
    }

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
}

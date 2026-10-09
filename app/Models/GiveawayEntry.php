<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $giveaway_id
 * @property int $user_id
 * @property bool $is_winner
 */
#[Fillable(['giveaway_id', 'user_id'])]
class GiveawayEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_winner' => 'boolean'];
    }

    /**
     * @return BelongsTo<Giveaway, $this>
     */
    public function giveaway(): BelongsTo
    {
        return $this->belongsTo(Giveaway::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

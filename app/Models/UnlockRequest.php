<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property string|null $description
 * @property Carbon|null $handled_at
 */
#[Fillable(['email', 'description', 'handled_at'])]
class UnlockRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}

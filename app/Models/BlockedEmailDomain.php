<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $domain
 * @property string|null $description
 */
#[Fillable(['domain', 'description'])]
class BlockedEmailDomain extends Model {}

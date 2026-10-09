<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $address
 * @property string|null $description
 */
#[Fillable(['address', 'description'])]
class BlockedIp extends Model {}

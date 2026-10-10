<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a member wants what they buy delivered. Private: besides the member,
 * only the seller of an auction they won gets to see it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $recipient_name
 * @property string $phone
 * @property int|null $city_id
 * @property string $address
 * @property-read City|null $city
 */
#[Fillable(['recipient_name', 'phone', 'city_id', 'address'])]
class ShippingAddress extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Street address and province on one line.
     */
    public function oneLine(): string
    {
        return implode(', ', array_filter([$this->address, $this->city?->name]));
    }
}

<?php

namespace App\Http\Requests\Listings;

use App\Enums\BidType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceBidRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bid_type' => ['required', Rule::enum(BidType::class)],
            'bid_price' => ['required_if:bid_type,'.BidType::Limit->value, 'nullable', 'integer', 'gt:0', 'max:999999999999'],
        ];
    }
}

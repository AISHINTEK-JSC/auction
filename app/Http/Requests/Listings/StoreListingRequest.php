<?php

namespace App\Http\Requests\Listings;

use App\Enums\AuctionType;
use App\Enums\ItemCondition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Mirrors the engine's own rules (AuctionRequest / AuctionCreationService) so the
 * seller gets field errors here instead of a rejected auction.
 */
class StoreListingRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $up = AuctionType::Up->value;
        $down = AuctionType::Down->value;

        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:50000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
            'condition' => ['required', Rule::enum(ItemCondition::class)],
            'images' => ['required', 'array', 'min:1', 'max:8'],
            'images.*' => ['image', 'max:5120'],
            'tags' => ['nullable', 'array', 'max:5'],
            'tags.*' => ['string', 'max:30'],

            'auction_type' => ['required', Rule::enum(AuctionType::class)],
            'start_time' => ['nullable', 'date', 'after:now'],
            'start_price' => ['required', 'integer', 'gt:0', 'max:999999999999'],

            'end_time' => ["required_if:auction_type,{$up}", 'nullable', 'date'],
            'min_bid_price' => ['nullable', 'integer', 'gt:start_price', 'max:999999999999'],
            'auto_extend' => ['boolean'],

            'down_minutes' => ["required_if:auction_type,{$down}", 'nullable', 'integer', 'min:1', 'max:1440'],
            'down_price' => ["required_if:auction_type,{$down}", 'nullable', 'integer', 'gt:0', 'lt:start_price'],
            'finish_price' => ['nullable', 'integer', 'gt:0', 'lt:start_price'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || (int) $this->input('auction_type') !== AuctionType::Up->value) {
                    return;
                }

                $start = $this->filled('start_time') ? Carbon::parse($this->input('start_time')) : now();

                if (Carbon::parse($this->input('end_time'))->lt($start->addMinutes(5))) {
                    $validator->errors()->add('end_time', __('engine.E_112'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['down_minutes' => 'chu kỳ giảm giá'];
    }
}

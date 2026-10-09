<?php

namespace App\Http\Requests\Listings;

use App\Enums\AuctionType;
use App\Enums\ItemCondition;
use App\Enums\SaleType;
use App\Models\Listing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A draft may be half filled in: only the title is required, so there is
 * something to list it by. Whatever is given must still be of the right kind;
 * the rules that tie fields together wait until it is published.
 */
class SaveDraftRequest extends FormRequest
{
    private const MAX_IMAGES = 8;

    public function authorize(): bool
    {
        $draft = $this->route('listing');

        return ! $draft instanceof Listing || $this->user()->can('manageDraft', $draft);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:50000'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'condition' => ['nullable', Rule::enum(ItemCondition::class)],
            'tags' => ['nullable', 'array', 'max:5'],
            'tags.*' => ['string', 'max:30'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['image', 'max:5120'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            'image_order' => ['nullable', 'array'],
            'image_order.*' => ['integer'],

            'sale_type' => ['nullable', Rule::enum(SaleType::class)],
            'is_negotiable' => ['boolean'],
            'auction_type' => ['nullable', Rule::enum(AuctionType::class)],
            'start_price' => ['nullable', 'integer', 'gte:0', 'max:999999999999'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date'],
            'min_bid_price' => ['nullable', 'integer', 'gt:0', 'max:999999999999'],
            'bid_step' => ['nullable', 'integer', 'gt:0', 'max:999999999999'],
            'auto_extend' => ['boolean'],
            'down_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'down_price' => ['nullable', 'integer', 'gt:0', 'max:999999999999'],
            'finish_price' => ['nullable', 'integer', 'gt:0', 'max:999999999999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $draft = $this->route('listing');
                $kept = $draft instanceof Listing
                    ? $draft->images()->whereNotIn('id', $this->input('remove_images', []))->count()
                    : 0;

                if ($kept + count(Arr::wrap($this->file('images'))) > self::MAX_IMAGES) {
                    $validator->errors()->add('images', 'Tin đăng chỉ được có tối đa '.self::MAX_IMAGES.' hình ảnh.');
                }
            },
        ];
    }
}

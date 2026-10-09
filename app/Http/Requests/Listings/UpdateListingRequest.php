<?php

namespace App\Http\Requests\Listings;

use App\Enums\ItemCondition;
use App\Models\Listing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * What a seller may change after posting. Prices and times belong to the
 * auction in the engine and are not editable.
 */
class UpdateListingRequest extends FormRequest
{
    private const MAX_IMAGES = 8;

    /**
     * Checked before validation, so a stranger gets a 403 and not field errors.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('listing'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:50000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
            'condition' => ['required', Rule::enum(ItemCondition::class)],
            'tags' => ['nullable', 'array', 'max:5'],
            'tags.*' => ['string', 'max:30'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['image', 'max:5120'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Listing $listing */
                $listing = $this->route('listing');

                $kept = $listing->images()->whereNotIn('id', $this->input('remove_images', []))->count();
                $total = $kept + count(Arr::wrap($this->file('images')));

                if ($total < 1) {
                    $validator->errors()->add('images', 'Tin đăng phải có ít nhất một hình ảnh.');
                } elseif ($total > self::MAX_IMAGES) {
                    $validator->errors()->add('images', 'Tin đăng chỉ được có tối đa '.self::MAX_IMAGES.' hình ảnh.');
                }
            },
        ];
    }
}

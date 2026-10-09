<?php

namespace App\Http\Requests\Listings;

use App\Enums\AuctionType;
use App\Enums\ItemCondition;
use App\Enums\SaleType;
use App\Models\Listing;
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
     * The finished listing being put up again, if any. Its images are reused,
     * so uploading new ones becomes optional.
     */
    public function source(): ?Listing
    {
        $id = $this->integer('relist_from');

        return $id > 0 ? Listing::query()->with('images')->find($id) : null;
    }

    public function isNegotiable(): bool
    {
        return $this->input('sale_type') === SaleType::Fixed->value && $this->boolean('is_negotiable');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $up = AuctionType::Up->value;
        $down = AuctionType::Down->value;
        $reused = $this->source()?->images->count() ?? 0;
        $auctionOnly = 'exclude_if:sale_type,'.SaleType::Fixed->value;

        return [
            'relist_from' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:50000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
            'condition' => ['required', Rule::enum(ItemCondition::class)],
            'images' => [$reused > 0 ? 'nullable' : 'required', 'array', 'max:'.max(0, 8 - $reused)],
            'images.*' => ['image', 'max:5120'],
            'tags' => ['nullable', 'array', 'max:5'],
            'tags.*' => ['string', 'max:30'],

            // 'fixed' is a plain post with a price; everything about the auction is then left out.
            'sale_type' => ['required', Rule::enum(SaleType::class)],
            // "Giá thoả thuận": only a fixed-price listing can go without a price.
            'is_negotiable' => ['boolean'],
            // The start price of an auction, or the asking price of a fixed-price listing.
            'start_price' => [
                Rule::excludeIf($this->isNegotiable()),
                'required',
                'integer',
                'gt:0',
                'max:999999999999',
            ],

            'auction_type' => [$auctionOnly, 'required', Rule::enum(AuctionType::class)],
            'start_time' => [$auctionOnly, 'nullable', 'date', 'after:now'],

            'end_time' => [$auctionOnly, "required_if:auction_type,{$up}", 'nullable', 'date'],
            'min_bid_price' => [$auctionOnly, 'nullable', 'integer', 'gt:start_price', 'max:999999999999'],
            // Not an engine field: this app enforces it when bids are placed.
            'bid_step' => [$auctionOnly, 'nullable', 'integer', 'gt:0', 'max:999999999999'],
            'auto_extend' => [$auctionOnly, 'boolean'],

            'down_minutes' => [$auctionOnly, "required_if:auction_type,{$down}", 'nullable', 'integer', 'min:1', 'max:1440'],
            'down_price' => [$auctionOnly, "required_if:auction_type,{$down}", 'nullable', 'integer', 'gt:0', 'lt:start_price'],
            'finish_price' => [$auctionOnly, 'nullable', 'integer', 'gt:0', 'lt:start_price'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $source = $this->source();

                if ($this->filled('relist_from') && ($source === null || $this->user()->cannot('relist', $source))) {
                    $validator->errors()->add('relist_from', 'Không thể đăng lại tin này.');
                }

                if ($validator->errors()->isNotEmpty()
                    || $this->input('sale_type') === SaleType::Fixed->value
                    || (int) $this->input('auction_type') !== AuctionType::Up->value) {
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
        return ['down_minutes' => 'chu kỳ giảm giá', 'bid_step' => 'bước giá', 'sale_type' => 'hình thức bán'];
    }
}

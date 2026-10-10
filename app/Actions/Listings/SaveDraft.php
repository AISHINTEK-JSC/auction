<?php

namespace App\Actions\Listings;

use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
use App\Enums\ItemCondition;
use App\Enums\ModerationStatus;
use App\Enums\SaleType;
use App\Models\Listing;
use App\Models\User;
use App\Services\ListingImages;
use App\Support\Brand;
use App\Support\HtmlCleaner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SaveDraft
{
    public function __construct(private readonly ListingImages $images) {}

    /**
     * Keep whatever the seller has filled in so far. A draft is never shown to
     * anyone else and nothing is sent to the auction engine; the full checks
     * happen when it is published (CreateListing).
     *
     * @param  array<string, mixed>  $data  validated SaveDraftRequest input; only the title is certain to be there
     * @param  list<UploadedFile>  $uploads
     * @param  Listing|null  $draft  the draft being saved again, or null for a new one
     */
    public function handle(User $seller, array $data, array $uploads, ?Listing $draft = null): Listing
    {
        $saleType = SaleType::tryFrom((string) ($data['sale_type'] ?? '')) ?? SaleType::Auction;
        $type = $saleType === SaleType::Auction ? AuctionType::tryFrom((int) ($data['auction_type'] ?? 0)) : null;
        $price = (int) ($data['start_price'] ?? 0);

        $attributes = [
            'user_id' => $seller->id,
            'title' => $data['title'],
            'description' => HtmlCleaner::clean($data['description'] ?? null),
            'category_id' => $data['category_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'condition' => ItemCondition::tryFrom((string) ($data['condition'] ?? '')) ?? ItemCondition::Used,
            'brand' => Brand::normalize($data['brand'] ?? null),
            'moderation_status' => ModerationStatus::Draft,
            'sale_type' => $saleType,
            'is_negotiable' => $saleType === SaleType::Fixed && ($data['is_negotiable'] ?? false),
            'auction_type' => $type,
            'start_price' => $price,
            'current_price' => $price,
            'min_bid_price' => $data['min_bid_price'] ?? null,
            'bid_step' => $data['bid_step'] ?? null,
            'start_time' => isset($data['start_time']) ? Carbon::parse($data['start_time']) : null,
            'end_time' => isset($data['end_time']) ? Carbon::parse($data['end_time']) : null,
            'auto_extend' => (bool) ($data['auto_extend'] ?? false),
            'down_time' => isset($data['down_minutes']) ? (int) $data['down_minutes'] * 60 : null,
            'down_price' => $data['down_price'] ?? null,
            'finish_price' => $data['finish_price'] ?? null,
            'auction_status' => AuctionStatus::Upcoming,
            'ends_at' => null,
        ];

        $stored = [];

        try {
            [$listing, $removed] = DB::transaction(function () use ($attributes, $data, $uploads, $draft, &$stored) {
                $listing = $draft ?? new Listing;
                $listing->fill($attributes)->save();
                $listing->tags()->sync(CreateListing::tagIds($data['tags'] ?? []));

                $changes = $this->images->sync($listing, $data['remove_images'] ?? [], $data['image_order'] ?? [], $uploads);
                $stored = $changes['stored'];

                return [$listing, $changes['removed']];
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($stored);

            throw $e;
        }

        Storage::disk('public')->delete($removed);

        return $listing;
    }
}

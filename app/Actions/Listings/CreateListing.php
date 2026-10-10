<?php

namespace App\Actions\Listings;

use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
use App\Enums\ModerationStatus;
use App\Enums\SaleType;
use App\Models\Listing;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use App\Services\ListingImages;
use App\Support\HtmlCleaner;
use App\Support\MarketplaceSettings;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CreateListing
{
    private const ENGINE_DATE_FORMAT = 'Y-m-d H:i';

    public function __construct(
        private readonly AuctionEngineClient $engine,
        private readonly ListingImages $images,
    ) {}

    /**
     * Store the listing with its images and tags and, unless it is a fixed-price
     * listing, open its auction in the engine. If the engine refuses, nothing is
     * kept: the transaction rolls back and the stored files are removed.
     *
     * When moderators approve listings first, the auction is not opened here:
     * the listing waits as "pending" and ListingModeration opens it on approval.
     *
     * @param  array<string, mixed>  $data  validated StoreListingRequest input
     * @param  list<UploadedFile>  $images
     * @param  Listing|null  $source  a finished listing being put up again; its images are copied
     * @param  Listing|null  $draft  a draft being published: it becomes the listing, keeping its images
     *
     * @throws EngineException
     */
    public function handle(User $seller, array $data, array $images, ?Listing $source = null, ?Listing $draft = null): Listing
    {
        $saleType = SaleType::from($data['sale_type']);
        // Null for a fixed-price listing: it has no auction, so none of the fields below apply.
        $type = $saleType === SaleType::Auction ? AuctionType::from((int) $data['auction_type']) : null;
        $startTime = isset($data['start_time']) ? Carbon::parse($data['start_time']) : null;
        $endTime = $type === AuctionType::Up ? Carbon::parse($data['end_time']) : null;
        $needsApproval = self::approvalRequired() && ! $seller->isStaff();
        // "Giá thoả thuận" stores no price; only fixed-price listings may use it.
        $negotiable = $saleType === SaleType::Fixed && ($data['is_negotiable'] ?? false);
        $price = $negotiable ? 0 : (int) $data['start_price'];

        $attributes = [
            'user_id' => $seller->id,
            'category_id' => $data['category_id'],
            'city_id' => $data['city_id'],
            'title' => $data['title'],
            'description' => HtmlCleaner::clean($data['description'] ?? null),
            'condition' => $data['condition'],
            'moderation_status' => $needsApproval ? ModerationStatus::Pending : ModerationStatus::Approved,
            'sale_type' => $saleType,
            'auction_type' => $type,
            'start_price' => $price,
            'is_negotiable' => $negotiable,
            'min_bid_price' => $type === AuctionType::Up ? ($data['min_bid_price'] ?? null) : null,
            'bid_step' => $type === AuctionType::Up ? ($data['bid_step'] ?? null) : null,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'auto_extend' => $type === AuctionType::Up && ($data['auto_extend'] ?? false),
            'down_time' => $type === AuctionType::Down ? (int) $data['down_minutes'] * 60 : null,
            'down_price' => $type === AuctionType::Down ? $data['down_price'] : null,
            'finish_price' => $type === AuctionType::Down ? ($data['finish_price'] ?? null) : null,
            // An auction starts as "upcoming" until the engine says otherwise;
            // a fixed-price listing is simply for sale.
            'auction_status' => $saleType === SaleType::Auction ? AuctionStatus::Upcoming : AuctionStatus::Running,
            'current_price' => $price,
            // An auction ends when the engine says so; a fixed-price listing after its lifetime.
            'ends_at' => $saleType === SaleType::Fixed ? self::fixedListingDeadline() : $endTime,
        ];

        $storedPaths = [];
        $removedPaths = [];
        $auctionId = null;

        try {
            $listing = DB::transaction(function () use ($attributes, $data, $images, $source, $draft, $saleType, $needsApproval, &$storedPaths, &$removedPaths, &$auctionId) {
                if ($draft !== null) {
                    $listing = $draft;
                    // It is posted now, not when the draft was started.
                    $listing->fill($attributes)->forceFill(['created_at' => now()])->save();

                    $changes = $this->images->sync($listing, $data['remove_images'] ?? [], $data['image_order'] ?? [], $images);
                    $storedPaths = $changes['stored'];
                    $removedPaths = $changes['removed'];
                } else {
                    $listing = Listing::query()->create($attributes + ['relisted_from_id' => $source?->id]);
                    $storedPaths = $this->storeImages($listing, $source, $images);
                }

                $listing->tags()->sync(self::tagIds($data['tags'] ?? []));

                if ($saleType === SaleType::Auction && ! $needsApproval) {
                    $auctionId = $this->openAuction($listing);
                }

                return $listing;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($storedPaths);

            if ($auctionId !== null) {
                // The engine accepted the auction but we failed to keep the listing: do not leave it running.
                rescue(fn () => $this->engine->cancelAuction($auctionId));
            }

            // A draft that could not be published is a draft again, with everything it had.
            $draft?->refresh();

            throw $e;
        }

        Storage::disk('public')->delete($removedPaths);

        return $listing;
    }

    /**
     * Create the listing's auction in the engine and link it.
     *
     * @throws EngineException
     */
    public function openAuction(Listing $listing): int
    {
        $auction = $this->engine->createAuction($this->enginePayload($listing));

        $listing->auction_id = (int) $auction['auction_id'];
        $listing->save();

        return $listing->auction_id;
    }

    /**
     * When a fixed-price listing posted (or approved) now stops being shown.
     */
    public static function fixedListingDeadline(): CarbonInterface
    {
        return now()->addDays(MarketplaceSettings::fixedListingDays());
    }

    public static function approvalRequired(): bool
    {
        return (bool) Setting::read(Setting::LISTING_APPROVAL, false);
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    public static function tagIds(array $names): array
    {
        return array_values(collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => Str::slug($name))
            ->map(fn (string $name) => Tag::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )->id)
            ->all());
    }

    /**
     * The images of a brand-new listing: those of the listing it was relisted
     * from, copied, followed by the uploads.
     *
     * @param  list<UploadedFile>  $uploads
     * @return list<string> the stored paths
     */
    private function storeImages(Listing $listing, ?Listing $source, array $uploads): array
    {
        $stored = [];
        $position = 0;

        foreach ($source->images ?? [] as $image) {
            $copy = ListingImages::copy($image, $listing, $position);

            if ($copy !== null) {
                array_push($stored, ...$copy->files());
                $position++;
            }
        }

        foreach ($uploads as $upload) {
            array_push($stored, ...ListingImages::add($listing, $upload, $position++)->files());
        }

        return $stored;
    }

    /**
     * @return array<string, mixed>
     */
    private function enginePayload(Listing $listing): array
    {
        $payload = [
            'seller_id' => $listing->user_id,
            'item_id' => $listing->id,
            'auction_type' => $listing->auction_type?->value,
            'start_price' => $listing->start_price,
            // Omitted start_time means "start now".
            'start_time' => $listing->start_time?->format(self::ENGINE_DATE_FORMAT),
        ];

        if ($listing->auction_type === AuctionType::Up) {
            $payload += [
                'end_time' => $listing->end_time?->format(self::ENGINE_DATE_FORMAT),
                'min_bid_price' => $listing->min_bid_price,
                'auto_extend_bid_time' => (int) $listing->auto_extend,
                'calculate_type' => config('services.auction_engine.calculate_type'),
            ];
        } else {
            $payload += [
                'down_time' => $listing->down_time,
                'down_price' => $listing->down_price,
                'finish_price' => $listing->finish_price,
            ];
        }

        return array_filter($payload, fn ($value) => $value !== null);
    }
}

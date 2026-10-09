<?php

namespace App\Actions\Listings;

use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
use App\Models\Listing;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Throwable;

class CreateListing
{
    private const ENGINE_DATE_FORMAT = 'Y-m-d H:i';

    public function __construct(private readonly AuctionEngineClient $engine) {}

    /**
     * Store the listing with its images and tags, then open its auction in the engine.
     * If the engine refuses, nothing is kept: the transaction rolls back and the
     * uploaded files are removed.
     *
     * @param  array<string, mixed>  $data  validated StoreListingRequest input
     * @param  list<UploadedFile>  $images
     *
     * @throws EngineException
     */
    public function handle(User $seller, array $data, array $images): Listing
    {
        $type = AuctionType::from((int) $data['auction_type']);
        $startTime = isset($data['start_time']) ? Carbon::parse($data['start_time']) : null;
        $endTime = $type === AuctionType::Up ? Carbon::parse($data['end_time']) : null;

        $storedPaths = [];
        $auctionId = null;

        try {
            return DB::transaction(function () use ($seller, $data, $images, $type, $startTime, $endTime, &$storedPaths, &$auctionId) {
                $listing = Listing::query()->create([
                    'user_id' => $seller->id,
                    'category_id' => $data['category_id'],
                    'city_id' => $data['city_id'],
                    'title' => $data['title'],
                    'description' => $this->sanitize($data['description'] ?? null),
                    'condition' => $data['condition'],
                    'auction_type' => $type,
                    'start_price' => $data['start_price'],
                    'min_bid_price' => $type === AuctionType::Up ? ($data['min_bid_price'] ?? null) : null,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'auto_extend' => $type === AuctionType::Up && ($data['auto_extend'] ?? false),
                    'down_time' => $type === AuctionType::Down ? (int) $data['down_minutes'] * 60 : null,
                    'down_price' => $type === AuctionType::Down ? $data['down_price'] : null,
                    'finish_price' => $type === AuctionType::Down ? ($data['finish_price'] ?? null) : null,
                    'auction_status' => AuctionStatus::Upcoming,
                    'current_price' => $data['start_price'],
                    'ends_at' => $endTime,
                ]);

                foreach ($images as $position => $image) {
                    $storedPaths[] = $path = $image->store("listings/{$listing->id}", 'public');
                    $listing->images()->create(['path' => $path, 'position' => $position]);
                }

                $listing->tags()->sync($this->tagIds($data['tags'] ?? []));

                $auction = $this->engine->createAuction($this->enginePayload($listing));
                $auctionId = (int) $auction['auction_id'];

                $listing->auction_id = $auctionId;
                $listing->save();

                return $listing;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($storedPaths);

            if ($auctionId !== null) {
                // The engine accepted the auction but we failed to keep the listing: do not leave it running.
                rescue(fn () => $this->engine->cancelAuction($auctionId));
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function enginePayload(Listing $listing): array
    {
        $payload = [
            'seller_id' => $listing->user_id,
            'item_id' => $listing->id,
            'auction_type' => $listing->auction_type->value,
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

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    private function tagIds(array $names): array
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
     * The description is rich text from the editor and is rendered with v-html.
     */
    private function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig)->allowSafeElements()->withMaxInputLength(50_000),
        );

        return $sanitizer->sanitize($html);
    }
}

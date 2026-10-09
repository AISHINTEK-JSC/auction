<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuctionStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateListingTest extends TestCase
{
    use EngineFake, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
        Storage::fake('public');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Máy ảnh cũ',
            'description' => '<p>Còn tốt</p><script>alert(1)</script>',
            'category_id' => Category::factory()->create()->id,
            'city_id' => City::query()->create(['name' => 'Hà Nội'])->id,
            'condition' => 'used',
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            'tags' => ['Máy ảnh', 'film'],
            'sale_type' => 'auction',
            'auction_type' => 1,
            'start_price' => 100000,
            'end_time' => now()->addDay()->format('Y-m-d\TH:i'),
            'auto_extend' => true,
            ...$overrides,
        ];
    }

    public function test_an_ascending_auction_is_created_in_the_engine_and_linked(): void
    {
        $seller = User::factory()->create();

        Http::fake([
            'engine.test/api/v1/auction' => Http::response($this->engineOk(['auction_id' => 42])),
            'engine.test/api/v1/auction/42' => Http::response($this->engineOk($this->engineAuction(42))),
        ]);

        $response = $this->actingAs($seller)->post(route('listings.store'), $this->payload());

        $listing = Listing::query()->sole();
        $response->assertRedirect(route('listings.show', $listing));

        $this->assertSame(42, $listing->auction_id);
        $this->assertSame(AuctionStatus::Running, $listing->auction_status);
        $this->assertSame('<p>Còn tốt</p>', $listing->description);
        $this->assertCount(2, $listing->images);
        $this->assertEqualsCanonicalizing(['may-anh', 'film'], $listing->tags->pluck('slug')->all());
        Storage::disk('public')->assertExists($listing->images->first()->path);

        Http::assertSent(function (Request $request) use ($seller, $listing) {
            if ($request->url() !== 'http://engine.test/api/v1/auction') {
                return false;
            }

            $data = $request->data();

            return $data['seller_id'] === $seller->id
                && $data['item_id'] === $listing->id
                && $data['auction_type'] === 1
                && $data['start_price'] === 100000
                && $data['auto_extend_bid_time'] === 1
                && $data['calculate_type'] === 2
                && $data['end_time'] === $listing->end_time->format('Y-m-d H:i')
                && ! array_key_exists('start_time', $data);
        });
    }

    public function test_a_descending_auction_sends_its_step_in_seconds(): void
    {
        Http::fake([
            'engine.test/api/v1/auction' => Http::response($this->engineOk(['auction_id' => 43])),
            'engine.test/api/v1/auction/43' => Http::response($this->engineOk($this->engineAuction(43, ['auction_type' => '2']))),
        ]);

        $this->actingAs(User::factory()->create())->post(route('listings.store'), $this->payload([
            'auction_type' => 2,
            'end_time' => null,
            'down_minutes' => 2,
            'down_price' => 10000,
            'finish_price' => 20000,
        ]))->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->url() !== 'http://engine.test/api/v1/auction'
            || ($request->data()['down_time'] === 120
                && $request->data()['down_price'] === 10000
                && $request->data()['finish_price'] === 20000
                && ! array_key_exists('end_time', $request->data())));

        $this->assertSame(120, Listing::query()->sole()->down_time);
    }

    public function test_the_bid_step_is_kept_by_the_app_and_never_sent_to_the_engine(): void
    {
        Http::fake([
            'engine.test/api/v1/auction' => Http::response($this->engineOk(['auction_id' => 44])),
            'engine.test/api/v1/auction/44' => Http::response($this->engineOk($this->engineAuction(44))),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('listings.store'), $this->payload(['bid_step' => 50000]))
            ->assertSessionHasNoErrors();

        $this->assertSame(50000, Listing::query()->sole()->bid_step);
        Http::assertSent(fn (Request $request) => $request->url() !== 'http://engine.test/api/v1/auction'
            || ! array_key_exists('bid_step', $request->data()));

        $this->actingAs(User::factory()->create())
            ->post(route('listings.store'), $this->payload(['bid_step' => 0]))
            ->assertSessionHasErrors('bid_step');
    }

    public function test_nothing_is_kept_when_the_engine_refuses_the_auction(): void
    {
        Http::fake(['engine.test/api/v1/auction' => Http::response($this->engineError('E_103'))]);

        $this->actingAs(User::factory()->create())
            ->post(route('listings.store'), $this->payload())
            ->assertSessionHasErrors(['engine' => 'Sản phẩm này đã có phiên đấu giá.']);

        $this->assertDatabaseCount('listings', 0);
        $this->assertDatabaseCount('listing_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_engine_field_errors_land_on_the_matching_form_field(): void
    {
        Http::fake(['engine.test/api/v1/auction' => Http::response([
            'success' => false,
            'message' => 'The given data was invalid.',
            'required' => ['auto_extend_bid_time' => ['invalid']],
        ])]);

        $this->actingAs(User::factory()->create())
            ->post(route('listings.store'), $this->payload())
            ->assertSessionHasErrors('auto_extend');
    }

    public function test_the_end_must_be_five_minutes_after_the_start(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('listings.store'), $this->payload(['end_time' => now()->addMinutes(3)->format('Y-m-d\TH:i')]))
            ->assertSessionHasErrors('end_time');

        Http::assertNothingSent();
    }

    public function test_guests_cannot_post_listings(): void
    {
        $this->get(route('listings.create'))->assertRedirect(route('login'));
        $this->post(route('listings.store'), [])->assertRedirect(route('login'));
    }
}

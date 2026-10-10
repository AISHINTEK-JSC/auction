<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\User;
use App\Services\SavedSearchMatcher;
use App\Support\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A listing may name the product's brand. It is optional and free text.
 */
class BrandTest extends TestCase
{
    use EngineFake, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
        Storage::fake('public');
        Http::fake();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'sale_type' => 'fixed',
            'title' => 'Đồng hồ cơ mặt xanh',
            'category_id' => Category::factory()->create()->id,
            'city_id' => City::query()->create(['name' => 'Hà Nội'])->id,
            'condition' => 'used',
            'images' => [UploadedFile::fake()->image('a.jpg')],
            'start_price' => 800000,
            ...$overrides,
        ];
    }

    public function test_a_listing_can_be_posted_with_or_without_a_brand(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->post(route('listings.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertNull(Listing::query()->sole()->brand);

        // Stray spaces are tidied away; only spaces is no brand at all.
        $this->actingAs($seller)->post(route('listings.store'), $this->payload(['brand' => '  Grand   Seiko ']))->assertSessionHasNoErrors();
        $this->actingAs($seller)->post(route('listings.store'), $this->payload(['brand' => '   ']))->assertSessionHasNoErrors();

        $this->assertSame([null, 'Grand Seiko', null], Listing::query()->orderBy('id')->pluck('brand')->all());

        $this->actingAs($seller)->post(route('listings.store'), $this->payload(['brand' => str_repeat('a', 61)]))
            ->assertSessionHasErrors('brand');
    }

    public function test_a_brand_is_spelt_the_way_it_already_is_on_the_site(): void
    {
        Listing::factory()->fixedPrice()->create(['brand' => 'Seiko']);

        $this->assertSame('Seiko', Brand::normalize('seiko'));
        $this->assertSame('Seiko', Brand::normalize(' SEIKO '));
        $this->assertSame('Casio', Brand::normalize('Casio'));
        $this->assertNull(Brand::normalize(null));
    }

    public function test_the_brand_shows_on_the_listing_and_is_found_by_search(): void
    {
        $listing = Listing::factory()->fixedPrice()->create(['title' => 'Đồng hồ cơ mặt xanh', 'brand' => 'Seiko']);
        Listing::factory()->fixedPrice()->create(['title' => 'Đồng hồ điện tử']);

        $response = $this->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page->where('listing.brand', 'Seiko'));

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', (string) $response->getContent(), $match);
        $this->assertSame(['@type' => 'Brand', 'name' => 'Seiko'], json_decode($match[1], true)['brand']);

        // The word is not in the title: the brand alone finds it.
        $this->get(route('listings.index', ['q' => 'seiko']))->assertInertia(fn (Assert $page) => $page
            ->has('listings.data', 1)
            ->where('listings.data.0.id', $listing->id));
        // Titles are still searched. (Same capitals as the titles: SQLite, used by the
        // tests, only ignores case for unaccented letters; MySQL ignores it for all.)
        $this->get(route('listings.index', ['q' => 'Đồng hồ']))->assertInertia(fn (Assert $page) => $page->has('listings.data', 2));
    }

    public function test_the_seller_can_change_or_clear_the_brand(): void
    {
        $listing = Listing::factory()->fixedPrice()->create([
            'brand' => 'Seiko',
            'category_id' => Category::factory()->create()->id,
            'city_id' => City::query()->create(['name' => 'Hà Nội'])->id,
        ]);
        $listing->images()->create(['path' => 'listings/1/a.jpg', 'position' => 1]);
        $fields = ['title' => $listing->title, 'category_id' => $listing->category_id, 'city_id' => $listing->city_id, 'condition' => 'used'];

        $this->actingAs($listing->user)->get(route('listings.edit', $listing))
            ->assertInertia(fn (Assert $page) => $page->where('listing.brand', 'Seiko')->where('brands', ['Seiko']));

        $this->actingAs($listing->user)->put(route('listings.update', $listing), [...$fields, 'brand' => 'Orient'])->assertSessionHasNoErrors();
        $this->assertSame('Orient', $listing->fresh()->brand);

        $this->actingAs($listing->user)->put(route('listings.update', $listing), [...$fields, 'brand' => ''])->assertSessionHasNoErrors();
        $this->assertNull($listing->fresh()->brand);
    }

    public function test_drafts_and_relisted_listings_carry_the_brand(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->post(route('listings.drafts.store'), ['title' => 'Tin nháp', 'brand' => 'Sony'])->assertSessionHasNoErrors();
        $draft = Listing::query()->sole();
        $this->assertSame('Sony', $draft->brand);
        $this->actingAs($seller)->get(route('listings.drafts.edit', $draft))
            ->assertInertia(fn (Assert $page) => $page->where('draft.brand', 'Sony'));

        $ended = Listing::factory()->fixedPrice()->for($seller)->status(AuctionStatus::Expired)->create(['brand' => 'Giant']);
        $this->actingAs($seller)->get(route('listings.relist', $ended))
            ->assertInertia(fn (Assert $page) => $page->where('source.brand', 'Giant'));
    }

    public function test_suggestions_come_from_visible_listings_most_used_first(): void
    {
        Listing::factory()->fixedPrice()->count(2)->create(['brand' => 'Sony']);
        Listing::factory()->fixedPrice()->create(['brand' => 'Apple']);
        Listing::factory()->fixedPrice()->create(['brand' => 'Bí mật', 'moderation_status' => ModerationStatus::Draft]);
        Listing::factory()->fixedPrice()->create(['brand' => null]);

        $this->assertSame(['Sony', 'Apple'], Brand::suggestions());

        $this->actingAs(User::factory()->create())->get(route('listings.create'))
            ->assertInertia(fn (Assert $page) => $page->where('brands', ['Sony', 'Apple']));
    }

    public function test_a_saved_keyword_also_matches_the_brand(): void
    {
        $watcher = User::factory()->create();
        $watcher->savedSearches()->create(['q' => 'seiko']);

        $listing = Listing::factory()->fixedPrice()->create(['title' => 'Đồng hồ cơ mặt xanh', 'brand' => 'Seiko']);

        $this->assertSame(1, app(SavedSearchMatcher::class)->listingPublished($listing));
        $this->assertSame(1, $watcher->notifications()->count());
    }
}

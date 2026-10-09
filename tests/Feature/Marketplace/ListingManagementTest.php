<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ListingManagementTest extends TestCase
{
    use EngineFake, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
        Storage::fake('public');
    }

    private function listingWithImage(array $attributes = []): Listing
    {
        $listing = Listing::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'city_id' => City::query()->create(['name' => 'Hà Nội'])->id,
            ...$attributes,
        ]);

        $path = UploadedFile::fake()->image('old.jpg')->store("listings/{$listing->id}", 'public');
        $listing->images()->create(['path' => $path, 'position' => 0]);

        return $listing;
    }

    public function test_the_seller_can_edit_content_and_swap_images_without_touching_the_engine(): void
    {
        Http::fake();

        $listing = $this->listingWithImage(['title' => 'Cũ', 'start_price' => 100000]);
        $oldImage = $listing->images()->sole();

        $this->actingAs($listing->user)->get(route('listings.edit', $listing))
            ->assertInertia(fn (Assert $page) => $page->component('listings/Edit')->where('listing.title', 'Cũ')->has('listing.images', 1));

        $this->actingAs($listing->user)->put(route('listings.update', $listing), [
            'title' => 'Mới',
            'description' => '<p>ok</p><script>x</script>',
            'category_id' => $listing->category_id,
            'city_id' => $listing->city_id,
            'condition' => 'like_new',
            'tags' => ['Hiếm'],
            'images' => [UploadedFile::fake()->image('new.jpg')],
            'remove_images' => [$oldImage->id],
            // Not editable: must be ignored.
            'start_price' => 1,
        ])->assertRedirect(route('listings.show', $listing));

        $listing->refresh();
        $this->assertSame('Mới', $listing->title);
        $this->assertSame('<p>ok</p>', $listing->description);
        $this->assertSame(100000, $listing->start_price);
        $this->assertSame(['hiem'], $listing->tags->pluck('slug')->all());
        $this->assertCount(1, $listing->images);
        $this->assertNotSame($oldImage->path, $listing->images->first()->path);
        Storage::disk('public')->assertMissing($oldImage->path);

        Http::assertNothingSent();
    }

    public function test_a_listing_must_keep_at_least_one_image_and_only_its_seller_can_edit(): void
    {
        $listing = $this->listingWithImage();
        $payload = [
            'title' => 'Mới',
            'category_id' => $listing->category_id,
            'city_id' => $listing->city_id,
            'condition' => 'used',
            'remove_images' => [$listing->images()->sole()->id],
        ];

        $this->actingAs($listing->user)->put(route('listings.update', $listing), $payload)
            ->assertSessionHasErrors('images');

        $this->actingAs(User::factory()->create())->put(route('listings.update', $listing), $payload)
            ->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('listings.edit', $listing))
            ->assertForbidden();
    }

    public function test_an_auction_without_a_winner_can_be_relisted_with_its_images(): void
    {
        $old = $this->listingWithImage(['auction_id' => 7, 'auction_status' => AuctionStatus::Expired, 'title' => 'Xe đạp']);

        Http::fake([
            'engine.test/api/v1/auction' => Http::response($this->engineOk(['auction_id' => 8])),
            'engine.test/api/v1/auction/8' => Http::response($this->engineOk($this->engineAuction(8))),
        ]);

        $this->actingAs($old->user)->get(route('listings.relist', $old))
            ->assertInertia(fn (Assert $page) => $page
                ->component('listings/Create')
                ->where('source.id', $old->id)
                ->where('source.title', 'Xe đạp')
                ->has('source.images', 1));

        // No new upload: the old images are reused.
        $this->actingAs($old->user)->post(route('listings.store'), [
            'relist_from' => $old->id,
            'title' => 'Xe đạp (đăng lại)',
            'category_id' => $old->category_id,
            'city_id' => $old->city_id,
            'condition' => 'used',
            'auction_type' => 1,
            'start_price' => 90000,
            'end_time' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $new = Listing::query()->where('relisted_from_id', $old->id)->sole();
        $this->assertSame(8, $new->auction_id);
        $this->assertCount(1, $new->images);
        $this->assertNotSame($old->images->first()->path, $new->images->first()->path);
        Storage::disk('public')->assertExists($new->images->first()->path);
        Storage::disk('public')->assertExists($old->images->first()->path);
    }

    public function test_running_sold_or_foreign_listings_cannot_be_relisted(): void
    {
        $running = $this->listingWithImage(['auction_id' => 1]);
        $sold = Listing::factory()->status(AuctionStatus::Completed)->create(['auction_id' => 2]);
        $foreign = Listing::factory()->status(AuctionStatus::Expired)->create(['auction_id' => 3]);

        $this->actingAs($running->user)->get(route('listings.relist', $running))->assertForbidden();
        $this->actingAs($sold->user)->get(route('listings.relist', $sold))->assertForbidden();
        $this->actingAs($running->user)->get(route('listings.relist', $foreign))->assertForbidden();

        Http::fake();

        $this->actingAs($running->user)->post(route('listings.store'), [
            'relist_from' => $foreign->id,
            'title' => 'x',
            'category_id' => $running->category_id,
            'city_id' => $running->city_id,
            'condition' => 'used',
            'auction_type' => 1,
            'start_price' => 90000,
            'end_time' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('relist_from');

        Http::assertNothingSent();
    }

    public function test_tag_and_member_pages_list_published_listings(): void
    {
        $tag = Tag::query()->create(['name' => 'Hiếm', 'slug' => 'hiem']);
        $seller = User::factory()->create(['name' => 'Chị Bán']);

        Listing::factory()->for($seller)->create(['title' => 'Có thẻ'])->tags()->attach($tag);
        Listing::factory()->for($seller)->create(['title' => 'Không thẻ']);
        Listing::factory()->for($seller)->create(['moderation_status' => ModerationStatus::Hidden])->tags()->attach($tag);

        $this->get(route('tags.show', $tag))->assertInertia(fn (Assert $page) => $page
            ->component('listings/Index')
            ->where('tag.slug', 'hiem')
            ->has('listings.data', 1)
            ->where('listings.data.0.title', 'Có thẻ'));

        $this->get(route('users.show', $seller))->assertInertia(fn (Assert $page) => $page
            ->component('users/Show')
            ->where('member.name', 'Chị Bán')
            ->where('member.listing_count', 2)
            ->has('listings.data', 2));
    }

    public function test_unknown_pages_get_the_marketplace_error_page(): void
    {
        $this->get('/khong-co-trang-nay')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ErrorPage')
                ->where('status', 404)
                ->has('categories'));

        $this->get(route('listings.show', 999999))->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('ErrorPage'));

        // The engine's webhook keeps its JSON answers.
        $this->post('/webhooks/auction')->assertUnauthorized()->assertJson(['success' => false]);
    }
}

<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A draft is a listing saved to finish later: only its seller sees it, and
 * the auction engine hears nothing until it is published.
 */
class DraftTest extends TestCase
{
    use EngineFake, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
        Storage::fake('public');
    }

    private function draft(User $seller, array $attributes = []): Listing
    {
        return Listing::factory()->for($seller)->create([
            'moderation_status' => ModerationStatus::Draft,
            'auction_id' => null,
            'auction_status' => AuctionStatus::Upcoming,
            'ends_at' => null,
            ...$attributes,
        ]);
    }

    /**
     * Everything a finished auction listing needs, images aside.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function complete(array $overrides = []): array
    {
        return [
            'title' => 'Máy ảnh cũ',
            'category_id' => Category::factory()->create()->id,
            'city_id' => City::query()->create(['name' => 'Hà Nội'])->id,
            'condition' => 'used',
            'sale_type' => 'auction',
            'auction_type' => 1,
            'start_price' => 100000,
            'end_time' => now()->addDay()->format('Y-m-d\TH:i'),
            ...$overrides,
        ];
    }

    public function test_a_title_is_all_it_takes_to_save_a_draft(): void
    {
        Http::fake();
        $seller = User::factory()->create();

        $response = $this->actingAs($seller)->post(route('listings.drafts.store'), [
            'title' => 'Máy ảnh cũ',
            'sale_type' => 'auction',
            'start_price' => '',
            'end_time' => '',
        ]);

        $draft = Listing::query()->sole();
        $response->assertRedirect(route('listings.drafts.edit', $draft));

        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isPublished());
        $this->assertNull($draft->auction_id);
        $this->assertNull($draft->category_id);
        Http::assertNothingSent();

        $this->actingAs($seller)->post(route('listings.drafts.store'), ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_a_draft_keeps_its_images_and_can_be_saved_again(): void
    {
        Http::fake();
        $seller = User::factory()->create();

        $this->actingAs($seller)->post(route('listings.drafts.store'), [
            'title' => 'Máy ảnh cũ',
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            'tags' => ['film'],
        ]);

        $draft = Listing::query()->sole();
        [$first, $second] = $draft->images->all();

        $this->actingAs($seller)->put(route('listings.drafts.update', $draft), [
            'title' => 'Máy ảnh film',
            'sale_type' => 'fixed',
            'start_price' => 500000,
            'remove_images' => [$first->id],
            'image_order' => [$second->id],
        ])->assertRedirect(route('listings.drafts.edit', $draft));

        $draft->refresh();
        $this->assertSame('Máy ảnh film', $draft->title);
        $this->assertTrue($draft->isDraft());
        $this->assertTrue($draft->isFixedPrice());
        $this->assertSame(500000, $draft->start_price);
        $this->assertSame([$second->id], $draft->images()->pluck('id')->all());
        Storage::disk('public')->assertMissing($first->path);
        Storage::disk('public')->assertExists($second->path);
    }

    public function test_the_form_reopens_with_what_was_saved(): void
    {
        $seller = User::factory()->create();
        $draft = $this->draft($seller, ['title' => 'Máy ảnh cũ', 'start_price' => 250000]);

        $this->actingAs($seller)->get(route('listings.drafts.edit', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('listings/Create')
                ->where('draft.id', $draft->id)
                ->where('draft.title', 'Máy ảnh cũ')
                ->where('draft.start_price', 250000)
                ->where('source', null));

        // The ordinary edit page sends a draft to its own form.
        $this->actingAs($seller)->get(route('listings.edit', $draft))
            ->assertRedirect(route('listings.drafts.edit', $draft));
    }

    public function test_nobody_else_sees_a_draft(): void
    {
        $seller = User::factory()->create();
        $draft = $this->draft($seller, ['title' => 'Tin chưa xong']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->get(route('listings.show', $draft))->assertNotFound();
        $this->actingAs($admin)->get(route('listings.show', $draft))->assertNotFound();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('running.data', 0)->has('upcoming', 0));
        $this->get(route('listings.index', ['status' => 'upcoming']))->assertInertia(fn (Assert $page) => $page->has('listings.data', 0));
        $this->get(route('users.show', $seller))->assertInertia(fn (Assert $page) => $page->has('listings.data', 0));
        $this->assertSame(0, ListingResource::getEloquentQuery()->count());

        $this->actingAs($seller)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('drafts', 1)
                ->where('drafts.0.title', 'Tin chưa xong')
                ->has('selling', 0)
                ->has('review', 0));
    }

    public function test_only_the_seller_can_touch_a_draft(): void
    {
        $draft = $this->draft(User::factory()->create());
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('listings.drafts.edit', $draft))->assertForbidden();
        $this->actingAs($other)->put(route('listings.drafts.update', $draft), ['title' => 'Của tôi'])->assertForbidden();
        $this->actingAs($other)->post(route('listings.drafts.publish', $draft), $this->complete())->assertForbidden();
        $this->actingAs($other)->delete(route('listings.drafts.destroy', $draft))->assertForbidden();

        $this->assertTrue($draft->fresh()->isDraft());
    }

    public function test_a_draft_cannot_be_bid_on_watched_or_managed_like_a_live_listing(): void
    {
        Http::fake();
        $seller = User::factory()->create();
        $draft = $this->draft($seller);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post(route('listings.bids.store', $draft), ['bid_type' => 1, 'bid_price' => 200000])->assertNotFound();
        $this->actingAs($seller)->delete(route('listings.destroy', $draft))->assertForbidden();

        Http::assertNothingSent();
        $this->assertTrue($draft->fresh()->isDraft());
    }

    public function test_publishing_runs_the_full_checks(): void
    {
        Http::fake();
        $seller = User::factory()->create();
        $draft = $this->draft($seller, ['title' => 'Máy ảnh cũ']);

        // No image yet, no category, no price.
        $this->actingAs($seller)->post(route('listings.drafts.publish', $draft), [
            'title' => 'Máy ảnh cũ',
            'sale_type' => 'auction',
            'auction_type' => 1,
        ])->assertSessionHasErrors(['category_id', 'city_id', 'images', 'start_price', 'end_time']);

        $this->assertTrue($draft->fresh()->isDraft());
        Http::assertNothingSent();
    }

    public function test_publishing_an_auction_draft_opens_the_auction_in_place(): void
    {
        $seller = User::factory()->create();
        $draft = $this->draft($seller, ['created_at' => now()->subWeek()]);
        $draft->images()->create(['path' => UploadedFile::fake()->image('old.jpg')->store('listings/'.$draft->id, 'public'), 'position' => 0]);

        Http::fake([
            'engine.test/api/v1/auction' => Http::response($this->engineOk(['auction_id' => 77])),
            'engine.test/api/v1/auction/77' => Http::response($this->engineOk($this->engineAuction(77))),
        ]);

        // The stored image counts: no new upload is needed.
        $this->actingAs($seller)->post(route('listings.drafts.publish', $draft), $this->complete(['title' => 'Máy ảnh đã xong']))
            ->assertRedirect(route('listings.show', $draft));

        $listing = Listing::query()->sole();
        $this->assertSame($draft->id, $listing->id);
        $this->assertSame('Máy ảnh đã xong', $listing->title);
        $this->assertSame(77, $listing->auction_id);
        $this->assertSame(AuctionStatus::Running, $listing->auction_status);
        $this->assertTrue($listing->isPublished());
        $this->assertCount(1, $listing->images);
        // Listed as new from the moment it is posted, not from when the draft began.
        $this->assertTrue($listing->created_at->gt(now()->subMinute()));

        $this->get(route('listings.index'))->assertInertia(fn (Assert $page) => $page->has('listings.data', 1));
    }

    public function test_publishing_a_fixed_price_draft_never_calls_the_engine(): void
    {
        Http::fake();
        $seller = User::factory()->create();
        $draft = $this->draft($seller);

        $this->actingAs($seller)->post(route('listings.drafts.publish', $draft), $this->complete([
            'sale_type' => 'fixed',
            'start_price' => 800000,
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ]))->assertRedirect(route('listings.show', $draft));

        $draft->refresh();
        $this->assertTrue($draft->isPublished());
        $this->assertTrue($draft->isFixedPrice());
        $this->assertSame(AuctionStatus::Running, $draft->auction_status);
        $this->assertSame(800000, $draft->current_price);
        $this->assertNotNull($draft->ends_at);
        Http::assertNothingSent();
    }

    public function test_a_draft_stays_a_draft_when_the_engine_refuses_the_auction(): void
    {
        $seller = User::factory()->create();
        $draft = $this->draft($seller, ['title' => 'Bản nháp']);

        Http::fake([
            'engine.test/api/v1/auction' => Http::response($this->engineError('E_99')),
        ]);

        $this->actingAs($seller)->post(route('listings.drafts.publish', $draft), $this->complete([
            'title' => 'Đã sửa',
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ]))->assertSessionHasErrors('engine');

        $draft->refresh();
        $this->assertTrue($draft->isDraft());
        $this->assertSame('Bản nháp', $draft->title);
        $this->assertNull($draft->auction_id);
        $this->assertCount(0, $draft->images);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_deleting_a_draft_removes_its_files(): void
    {
        $seller = User::factory()->create();
        $draft = $this->draft($seller);
        $path = UploadedFile::fake()->image('a.jpg')->store('listings/'.$draft->id, 'public');
        $draft->images()->create(['path' => $path, 'position' => 0]);

        $this->actingAs($seller)->delete(route('listings.drafts.destroy', $draft))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, Listing::query()->count());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_member_can_keep_twenty_drafts_at_most(): void
    {
        $seller = User::factory()->create();
        Listing::factory()->for($seller)->count(20)->create([
            'moderation_status' => ModerationStatus::Draft,
            'auction_id' => null,
        ]);

        $this->actingAs($seller)->post(route('listings.drafts.store'), ['title' => 'Thêm một tin'])
            ->assertSessionHasErrors('title');

        $this->assertSame(20, Listing::query()->count());
    }
}

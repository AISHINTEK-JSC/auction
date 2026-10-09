<?php

namespace Tests\Feature\Marketplace;

use App\Enums\ModerationStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\Setting;
use App\Models\User;
use App\Services\ListingModeration;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModerationFlowTest extends TestCase
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
            'category_id' => Category::factory()->create()->id,
            'city_id' => City::query()->create(['name' => 'Hà Nội'])->id,
            'condition' => 'used',
            'images' => [UploadedFile::fake()->image('a.jpg')],
            'sale_type' => 'auction',
            'auction_type' => 1,
            'start_price' => 100000,
            'end_time' => now()->addDay()->format('Y-m-d\TH:i'),
            ...$overrides,
        ];
    }

    public function test_with_approval_on_a_new_listing_waits_and_no_auction_is_opened(): void
    {
        Setting::write(Setting::LISTING_APPROVAL, true);
        Http::fake();

        $seller = User::factory()->create();

        $this->actingAs($seller)->post(route('listings.store'), $this->payload())
            ->assertRedirect(route('dashboard'));

        $listing = Listing::query()->sole();
        $this->assertSame(ModerationStatus::Pending, $listing->moderation_status);
        $this->assertNull($listing->auction_id);
        Http::assertNothingSent();

        // Not public, but its seller sees it under "Chờ duyệt".
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('running.data', 0));
        $this->get(route('listings.show', $listing))->assertNotFound();
        $this->actingAs($seller)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('review', 1)->has('selling', 0));
    }

    public function test_approving_opens_the_auction_and_tells_the_seller(): void
    {
        Setting::write(Setting::LISTING_APPROVAL, true);

        // Nothing is faked yet: posting must not reach the engine at all.
        $seller = User::factory()->create();
        $this->actingAs($seller)->post(route('listings.store'), $this->payload());
        $listing = Listing::query()->sole();

        Http::fake([
            'engine.test/api/v1/auction' => Http::response($this->engineOk(['auction_id' => 42])),
            'engine.test/api/v1/auction/42' => Http::response($this->engineOk($this->engineAuction(42))),
            'engine.test/api/v1/auction/42/bids' => Http::response($this->engineOk([])),
        ]);

        app(ListingModeration::class)->approve($listing);

        $listing->refresh();
        $this->assertSame(42, $listing->auction_id);
        $this->assertTrue($listing->isPublished());
        $this->assertSame('moderation', $seller->notifications()->sole()->data['kind']);
        $this->get(route('listings.show', $listing))->assertOk();
    }

    public function test_a_listing_whose_end_time_passed_while_waiting_cannot_be_approved(): void
    {
        Http::fake();

        $listing = Listing::factory()->create([
            'auction_id' => null,
            'moderation_status' => ModerationStatus::Pending,
            'end_time' => now()->addMinutes(2),
        ]);

        try {
            app(ListingModeration::class)->approve($listing);
            $this->fail('Expected a DomainException.');
        } catch (DomainException) {
            $this->assertSame(ModerationStatus::Pending, $listing->refresh()->moderation_status);
        }

        Http::assertNothingSent();
    }

    public function test_staff_post_without_waiting_even_when_approval_is_on(): void
    {
        Setting::write(Setting::LISTING_APPROVAL, true);

        Http::fake([
            'engine.test/api/v1/auction' => Http::response($this->engineOk(['auction_id' => 42])),
            'engine.test/api/v1/auction/42' => Http::response($this->engineOk($this->engineAuction(42))),
        ]);

        $this->actingAs(User::factory()->moderator()->create())->post(route('listings.store'), $this->payload());

        $this->assertTrue(Listing::query()->sole()->isPublished());
    }

    public function test_a_rejected_listing_returns_to_the_queue_when_its_seller_edits_it(): void
    {
        $listing = Listing::factory()->create(['auction_id' => null, 'moderation_status' => ModerationStatus::Pending]);
        $listing->images()->create(['path' => 'listings/x.jpg', 'position' => 0]);

        app(ListingModeration::class)->reject($listing, 'Ảnh mờ');

        $this->assertSame(ModerationStatus::Rejected, $listing->refresh()->moderation_status);
        $this->assertStringContainsString('Ảnh mờ', $listing->user->notifications()->sole()->data['body']);

        $this->actingAs($listing->user)->put(route('listings.update', $listing), [
            'title' => 'Đã sửa',
            'category_id' => Category::factory()->create()->id,
            'city_id' => City::query()->create(['name' => 'Huế'])->id,
            'condition' => 'used',
        ])->assertRedirect(route('dashboard'));

        $listing->refresh();
        $this->assertSame(ModerationStatus::Pending, $listing->moderation_status);
        $this->assertNull($listing->moderation_note);
    }

    public function test_a_hidden_listing_disappears_for_the_public_but_not_for_seller_and_staff(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7]);

        Http::fake([
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7))),
            'engine.test/api/v1/auction/7/bids' => Http::response($this->engineOk([])),
        ]);

        app(ListingModeration::class)->hide($listing, 'Hàng cấm');

        $this->get(route('listings.show', $listing))->assertNotFound();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('running.data', 0));
        $this->actingAs(User::factory()->create())->post(route('listings.bids.store', $listing), ['bid_type' => 2])->assertNotFound();

        $this->actingAs($listing->user)->get(route('listings.show', $listing))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('listing.moderation', 'hidden')->where('listing.moderation_note', 'Hàng cấm'));
        $this->actingAs(User::factory()->moderator()->create())->get(route('listings.show', $listing))->assertOk();
    }

    public function test_a_member_reports_a_listing_once(): void
    {
        $listing = Listing::factory()->create();
        $reporter = User::factory()->create();

        $this->post(route('listings.reports.store', $listing), ['reason' => 'scam'])->assertRedirect(route('login'));

        $this->actingAs($reporter)->post(route('listings.reports.store', $listing), ['reason' => 'scam', 'note' => 'Giá ảo'])
            ->assertSessionHasNoErrors();
        $this->actingAs($reporter)->post(route('listings.reports.store', $listing), ['reason' => 'counterfeit'])
            ->assertSessionHasNoErrors();

        $report = ListingReport::query()->sole();
        $this->assertSame('counterfeit', $report->reason->value);

        $this->actingAs($reporter)->post(route('listings.reports.store', $listing), ['reason' => 'nonsense'])
            ->assertSessionHasErrors('reason');
    }
}

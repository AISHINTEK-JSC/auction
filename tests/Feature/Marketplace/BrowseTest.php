<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuctionStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BrowseTest extends TestCase
{
    use EngineFake, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
    }

    public function test_the_home_page_lists_running_auctions_without_calling_the_engine(): void
    {
        Http::fake();

        Listing::factory()->create(['title' => 'Đang chạy']);
        Listing::factory()->status(AuctionStatus::Upcoming)->create(['title' => 'Sắp tới']);
        Listing::factory()->status(AuctionStatus::Expired)->create();
        Listing::factory()->create(['auction_id' => null]);

        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('running.data', 1)
            ->where('running.data.0.title', 'Đang chạy')
            ->has('upcoming', 1)
            ->has('categories')
            ->has('cities'));

        Http::assertNothingSent();
    }

    public function test_search_filters_by_title_status_and_category(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        Listing::factory()->create(['title' => 'Xe đạp Nhật', 'category_id' => $child->id]);
        Listing::factory()->create(['title' => 'Đồng hồ cổ']);
        Listing::factory()->status(AuctionStatus::Completed)->create(['title' => 'Xe đạp đã bán']);

        $this->get(route('listings.index', ['q' => 'xe đạp']))->assertInertia(fn (Assert $page) => $page
            ->component('listings/Index')
            ->has('listings.data', 1)
            ->where('listings.data.0.title', 'Xe đạp Nhật'));

        $this->get(route('listings.index', ['q' => 'xe đạp', 'status' => 'ended']))->assertInertia(fn (Assert $page) => $page
            ->where('listings.data.0.title', 'Xe đạp đã bán'));

        // A parent category includes its children.
        $this->get(route('categories.show', $parent))->assertInertia(fn (Assert $page) => $page
            ->has('listings.data', 1)
            ->where('category.slug', $parent->slug));
    }

    public function test_the_detail_page_shows_live_engine_state_and_hides_the_reserve_price(): void
    {
        $bidder = User::factory()->create(['name' => 'Người Mua']);
        $listing = Listing::factory()->create(['auction_id' => 7, 'min_bid_price' => 900000]);

        Http::fake([
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, [
                'current_price' => 180000,
                'bid_number' => 2,
                'remaining_time' => 120,
                'highest_bidder_id' => (string) $bidder->id,
            ]))),
            'engine.test/api/v1/auction/7/bids' => Http::response($this->engineOk([
                ['bid_id' => 1, 'bid_time' => '2026-10-09 10:00:00', 'bid_type' => '1', 'bid_price' => 150000, 'bidder_id' => (string) $bidder->id, 'current_price' => 150000, 'is_cancel' => false],
                ['bid_id' => 2, 'bid_time' => '2026-10-09 10:05:00', 'bid_type' => '1', 'bid_price' => 170000, 'bidder_id' => '999', 'current_price' => 170000, 'is_cancel' => true],
                ['bid_id' => 3, 'bid_time' => '2026-10-09 10:06:00', 'bid_type' => '1', 'bid_price' => 180000, 'bidder_id' => (string) $bidder->id, 'current_price' => 180000, 'is_cancel' => false],
            ])),
        ]);

        $this->get(route('listings.show', $listing))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('listings/Show')
            ->where('auction.current_price', 180000)
            ->where('auction.remaining_time', 120)
            ->where('auction.stale', false)
            ->has('bids', 2)
            ->where('bids.0.price', 180000)
            ->where('bids.0.bidder', 'Người Mua')
            ->where('listing.min_bid_price', null)
            ->where('listing.is_owner', false));

        $this->actingAs($listing->user)->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page
                ->where('listing.min_bid_price', 900000)
                ->where('listing.is_owner', true));

        // The live read also refreshed the snapshot used by list pages.
        $this->assertSame(180000, $listing->refresh()->current_price);
    }

    public function test_the_detail_page_falls_back_to_the_snapshot_when_the_engine_is_down(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7, 'current_price' => 123000]);

        Http::fake(['*' => Http::response('Bad gateway', 502)]);

        $this->get(route('listings.show', $listing))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auction.current_price', 123000)
            ->where('auction.stale', true)
            ->has('bids', 0));
    }

    public function test_a_listing_that_never_reached_the_engine_is_not_found(): void
    {
        $this->get(route('listings.show', Listing::factory()->create(['auction_id' => null])))->assertNotFound();
    }

    public function test_the_seller_can_cancel_an_open_auction_but_nobody_else_can(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7]);

        Http::fake([
            'engine.test/api/v1/auction/7/cancel' => Http::response($this->engineOk(['auction_id' => 7])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, [
                'remaining_time' => null,
                'status' => ['status' => 3, 'description' => ''],
            ]))),
        ]);

        $this->actingAs(User::factory()->create())
            ->delete(route('listings.destroy', $listing))
            ->assertForbidden();

        $this->actingAs($listing->user)
            ->delete(route('listings.destroy', $listing))
            ->assertRedirect();

        $this->assertSame(AuctionStatus::CancelledBySeller, $listing->refresh()->auction_status);
    }

    public function test_the_dashboard_groups_my_auctions(): void
    {
        $user = User::factory()->create();

        Listing::factory()->for($user)->create();
        Listing::factory()->for($user)->status(AuctionStatus::Expired)->create();
        Listing::factory()->status(AuctionStatus::Completed)->create(['winner_id' => $user->id]);
        Listing::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('selling', 1)
            ->has('ended', 1)
            ->has('won', 1));
    }
}

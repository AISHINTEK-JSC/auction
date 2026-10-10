<?php

namespace Tests\Feature\Marketplace;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BiddingTest extends TestCase
{
    use EngineFake, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
    }

    public function test_a_bid_is_placed_as_the_signed_in_user_and_the_snapshot_is_refreshed(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7]);
        $bidder = User::factory()->create();

        Http::fake([
            'engine.test/api/v1/bid' => Http::response($this->engineOk(['auction_id' => 7, 'bidder_id' => $bidder->id])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, [
                'current_price' => 150000,
                'bid_number' => 1,
            ]))),
        ]);

        $this->actingAs($bidder)
            ->from(route('listings.show', $listing))
            ->post(route('listings.bids.store', $listing), ['bid_type' => 1, 'bid_price' => 150000])
            ->assertRedirect(route('listings.show', $listing))
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://engine.test/api/v1/bid'
            && $request->data() === [
                'auction_id' => 7,
                'bidder_id' => $bidder->id,
                'bid_type' => 1,
                'bid_price' => 150000,
            ]);

        $listing->refresh();
        $this->assertSame(150000, $listing->current_price);
        $this->assertSame(1, $listing->bid_count);
    }

    public function test_an_engine_rejection_is_shown_on_the_price_field(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7]);

        Http::fake([
            'engine.test/api/v1/bid' => Http::response($this->engineError('E_119')),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7))),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('listings.bids.store', $listing), ['bid_type' => 1, 'bid_price' => 150000])
            ->assertSessionHasErrors(['bid_price' => 'Phiên đấu giá đã kết thúc.']);
    }

    public function test_the_seller_cannot_bid_on_their_own_listing(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7]);

        Http::fake();

        $this->actingAs($listing->user)
            ->post(route('listings.bids.store', $listing), ['bid_type' => 2])
            ->assertSessionHasErrors('bid_price');

        Http::assertNothingSent();
    }

    public function test_with_a_seller_step_a_bid_must_reach_current_price_plus_step(): void
    {
        $leader = User::factory()->create();
        $listing = Listing::factory()->create(['auction_id' => 7, 'bid_step' => 50000]);

        Http::fake([
            'engine.test/api/v1/bid' => Http::response($this->engineOk(['auction_id' => 7])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, [
                'current_price' => 200000,
                'bid_number' => 1,
                'highest_bidder_id' => (string) $leader->id,
            ]))),
        ]);

        $bidder = User::factory()->create();

        // 200.000 + 50.000 is the floor: one đồng less never reaches the engine.
        $this->actingAs($bidder)
            ->post(route('listings.bids.store', $listing), ['bid_type' => 1, 'bid_price' => 249999])
            ->assertSessionHasErrors(['bid_price' => 'Giá đặt tối thiểu là 250.000 đ (bước giá 50.000 đ).']);

        Http::assertNotSent(fn (Request $request) => $request->url() === 'http://engine.test/api/v1/bid');

        $this->actingAs($bidder)
            ->post(route('listings.bids.store', $listing), ['bid_type' => 1, 'bid_price' => 250000])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://engine.test/api/v1/bid'
            && $request->data()['bid_type'] === 1
            && $request->data()['bid_price'] === 250000);
    }

    public function test_with_a_seller_step_a_quick_bid_is_placed_at_exactly_the_floor(): void
    {
        $leader = User::factory()->create();
        $listing = Listing::factory()->create(['auction_id' => 7, 'bid_step' => 50000]);

        Http::fake([
            'engine.test/api/v1/bid' => Http::response($this->engineOk(['auction_id' => 7])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, [
                'current_price' => 200000,
                'bid_number' => 1,
                'highest_bidder_id' => (string) $leader->id,
            ]))),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('listings.bids.store', $listing), ['bid_type' => 2])
            ->assertSessionHasNoErrors();

        // Sent as a named price, not as the engine's own quick bid (which adds only 100 đ).
        Http::assertSent(fn (Request $request) => $request->url() === 'http://engine.test/api/v1/bid'
            && $request->data()['bid_type'] === 1
            && $request->data()['bid_price'] === 250000);
    }

    public function test_the_opening_bid_may_equal_the_start_price_even_with_a_step(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7, 'bid_step' => 50000]);

        Http::fake([
            'engine.test/api/v1/bid' => Http::response($this->engineOk(['auction_id' => 7])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, ['current_price' => 100000]))),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('listings.bids.store', $listing), ['bid_type' => 1, 'bid_price' => 100000])
            ->assertSessionHasNoErrors();
    }

    public function test_no_quick_bid_while_the_sellers_floor_price_is_unmet(): void
    {
        // With or without a step: the amount would be one the bidder cannot be shown.
        foreach ([50000, null] as $step) {
            $listing = Listing::factory()->create(['auction_id' => 7, 'bid_step' => $step, 'min_bid_price' => 180000]);

            Http::fake([
                'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, ['current_price' => 100000]))),
            ]);

            $this->actingAs(User::factory()->create())
                ->post(route('listings.bids.store', $listing), ['bid_type' => 2])
                ->assertSessionHasErrors('bid_price');

            Http::assertNotSent(fn (Request $request) => $request->url() === 'http://engine.test/api/v1/bid');
            $listing->delete();
        }
    }

    public function test_a_typed_bid_below_the_floor_price_gets_the_engines_answer_and_one_above_goes_through(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7, 'bid_step' => 50000, 'min_bid_price' => 180000]);
        $bidder = User::factory()->create();

        Http::fake([
            'engine.test/api/v1/bid' => Http::sequence()
                ->push($this->engineError('E_120'))
                ->push($this->engineOk(['auction_id' => 7])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, ['current_price' => 100000]))),
        ]);

        $this->actingAs($bidder)->post(route('listings.bids.store', $listing), ['bid_type' => 1, 'bid_price' => 100000])
            ->assertSessionHasErrors(['bid_price' => __('engine.E_120')]);

        $this->actingAs($bidder)->post(route('listings.bids.store', $listing), ['bid_type' => 1, 'bid_price' => 180000])
            ->assertSessionHasNoErrors();
    }

    public function test_once_the_floor_price_is_met_quick_bids_work_again(): void
    {
        $leader = User::factory()->create();
        $listing = Listing::factory()->create(['auction_id' => 7, 'bid_step' => 50000, 'min_bid_price' => 180000]);

        Http::fake([
            'engine.test/api/v1/bid' => Http::response($this->engineOk(['auction_id' => 7])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, [
                'current_price' => 180000,
                'bid_number' => 1,
                'highest_bidder_id' => (string) $leader->id,
            ]))),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('listings.bids.store', $listing), ['bid_type' => 2])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://engine.test/api/v1/bid'
            && $request->data()['bid_price'] === 230000);
    }

    public function test_bidders_learn_that_there_is_a_floor_price_but_not_its_amount(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7, 'min_bid_price' => 180000]);
        $plain = Listing::factory()->create(['auction_id' => 8]);

        Http::fake([
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7))),
            'engine.test/api/v1/auction/7/bids' => Http::response($this->engineOk([])),
            'engine.test/api/v1/auction/8' => Http::response($this->engineOk($this->engineAuction(8))),
            'engine.test/api/v1/auction/8/bids' => Http::response($this->engineOk([])),
        ]);

        $this->actingAs(User::factory()->create())->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page->where('listing.has_reserve', true)->where('listing.min_bid_price', null))
            ->assertDontSee('180000', false);

        $this->actingAs($listing->user)->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page->where('listing.min_bid_price', 180000));

        $this->get(route('listings.show', $plain))
            ->assertInertia(fn (Assert $page) => $page->where('listing.has_reserve', false));
    }

    public function test_guests_and_unverified_users_cannot_bid(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7]);

        Http::fake();

        $this->post(route('listings.bids.store', $listing), ['bid_type' => 2])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('listings.bids.store', $listing), ['bid_type' => 2])
            ->assertRedirect(route('verification.notice'));

        Http::assertNothingSent();
    }

    public function test_a_limit_bid_needs_a_price(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7]);

        Http::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('listings.bids.store', $listing), ['bid_type' => 1])
            ->assertSessionHasErrors('bid_price');

        Http::assertNothingSent();
    }
}

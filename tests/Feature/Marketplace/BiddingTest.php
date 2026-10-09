<?php

namespace Tests\Feature\Marketplace;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

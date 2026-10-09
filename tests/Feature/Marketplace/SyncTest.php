<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuctionStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use EngineFake, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
    }

    public function test_the_sync_command_updates_open_listings_from_one_list_call(): void
    {
        $winner = User::factory()->create();
        $running = Listing::factory()->create(['auction_id' => 1]);
        $won = Listing::factory()->create(['auction_id' => 2]);
        $closed = Listing::factory()->status(AuctionStatus::Expired)->create(['auction_id' => 3, 'current_price' => 5]);

        Http::fake(['engine.test/api/v1/auctions' => Http::response($this->engineOk([
            $this->engineAuction(1, ['current_price' => 250000, 'bid_number' => 4, 'remaining_time' => 600]),
            $this->engineAuction(2, [
                'current_price' => 300000,
                'bid_number' => 2,
                'remaining_time' => null,
                'status' => ['status' => 2, 'description' => ''],
                'bid_winner' => ['bidder_id' => (string) $winner->id, 'bid_price' => 300000, 'bid_time' => '2026-10-09 11:00:00'],
            ]),
            $this->engineAuction(3, ['current_price' => 999]),
            $this->engineAuction(99),
        ]))]);

        $this->artisan('auctions:sync')->assertSuccessful();

        Http::assertSentCount(1);

        $running->refresh();
        $this->assertSame(250000, $running->current_price);
        $this->assertSame(4, $running->bid_count);
        $this->assertEqualsWithDelta(600, now()->diffInSeconds($running->ends_at), 5);

        $won->refresh();
        $this->assertSame(AuctionStatus::Completed, $won->auction_status);
        $this->assertSame($winner->id, $won->winner_id);
        $this->assertSame(300000, $won->winning_price);

        // Already closed listings are left alone.
        $this->assertSame(5, $closed->refresh()->current_price);
    }

    public function test_the_sync_command_fails_cleanly_when_the_engine_is_down(): void
    {
        Listing::factory()->create(['auction_id' => 1]);

        Http::fake(['*' => Http::response('Bad gateway', 502)]);

        $this->artisan('auctions:sync')->assertFailed();
    }

    public function test_the_webhook_rejects_a_wrong_key(): void
    {
        Http::fake();

        $this->withToken('wrong')
            ->get('/webhooks/auction?auction[auction_id]=1')
            ->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_the_webhook_reads_the_form_body_of_a_get_and_refreshes_from_the_engine(): void
    {
        $winner = User::factory()->create();
        $listing = Listing::factory()->create(['auction_id' => 7]);

        Http::fake([
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, [
                'current_price' => 500000,
                'bid_number' => 3,
                'remaining_time' => null,
                'status' => ['status' => 2, 'description' => ''],
            ]))),
            'engine.test/api/v1/auction/7/winner' => Http::response($this->engineOk([
                'auction_id' => 7,
                'bid_winner' => ['bid_id' => 9, 'bidder_id' => (string) $winner->id, 'bid_type' => '1', 'bid_price' => 500000, 'bid_time' => '2026-10-09 11:00:00'],
            ])),
        ]);

        // The engine sends GET with a url-encoded body. The payload claims a wrong
        // price and bidder on purpose: only the engine's own answer may be stored.
        $this->call(
            'GET',
            '/webhooks/auction',
            server: ['HTTP_AUTHORIZATION' => 'Bearer push-key', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            content: http_build_query(['argument' => 'buy', 'auction' => ['auction_id' => 7, 'current_price' => 1, 'bidder_id' => 12345]]),
        )->assertOk()->assertJson(['success' => true, 'listing_id' => $listing->id]);

        $listing->refresh();
        $this->assertSame(AuctionStatus::Completed, $listing->auction_status);
        $this->assertSame(500000, $listing->current_price);
        $this->assertSame($winner->id, $listing->winner_id);
    }

    public function test_the_webhook_looks_again_when_the_engine_has_not_stored_the_final_status_yet(): void
    {
        Sleep::fake();

        $listing = Listing::factory()->create(['auction_id' => 7]);

        // The engine pushes first and only then marks the auction as finished.
        Http::fake(['engine.test/api/v1/auction/7' => Http::sequence()
            ->push($this->engineOk($this->engineAuction(7, ['remaining_time' => 0])))
            ->push($this->engineOk($this->engineAuction(7, [
                'remaining_time' => null,
                'status' => ['status' => 5, 'description' => ''],
            ]))),
        ]);

        $this->withToken('push-key')
            ->post('/webhooks/auction', ['argument' => 'end', 'auction' => ['auction_id' => 7]])
            ->assertOk();

        Sleep::assertSleptTimes(1);
        $this->assertSame(AuctionStatus::Expired, $listing->refresh()->auction_status);
    }

    public function test_the_webhook_answers_404_for_an_unknown_auction(): void
    {
        Http::fake();

        $this->withToken('push-key')
            ->post('/webhooks/auction', ['auction' => ['auction_id' => 123]])
            ->assertNotFound();
    }
}

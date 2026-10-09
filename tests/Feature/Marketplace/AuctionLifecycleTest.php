<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuctionStatus;
use App\Models\Listing;
use App\Models\Review;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Services\ForfeitPenalty;
use App\Services\ListingSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuctionLifecycleTest extends TestCase
{
    use EngineFake, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
    }

    private function kinds(User $user): array
    {
        return $user->notifications()->get()->map(fn ($notification) => $notification->data['kind'])->all();
    }

    public function test_the_previous_leader_is_told_when_outbid_and_the_bidder_is_remembered(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $listing = Listing::factory()->create(['auction_id' => 7, 'leader_id' => $first->id]);

        Http::fake([
            'engine.test/api/v1/bid' => Http::response($this->engineOk(['auction_id' => 7])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, [
                'current_price' => 200000,
                'bid_number' => 2,
                'highest_bidder_id' => (string) $second->id,
            ]))),
        ]);

        $this->actingAs($second)->post(route('listings.bids.store', $listing), ['bid_type' => 1, 'bid_price' => 200000])
            ->assertSessionHasNoErrors();

        $this->assertSame(['outbid'], $this->kinds($first));
        $this->assertSame([], $this->kinds($second));
        $this->assertSame($second->id, $listing->refresh()->leader_id);
        $this->assertSame(200000, (int) $second->bidListings()->sole()->pivot->last_bid_price);

        $this->actingAs($second)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('bidding', 1)
            ->where('bidding.0.leading', true));

        $this->actingAs($second)->get(route('notifications.index'))->assertInertia(fn (Assert $page) => $page
            ->component('notifications/Index')
            ->has('notifications.data', 0));
        $this->actingAs($first)->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 1));
    }

    public function test_everyone_involved_is_notified_once_when_an_auction_closes(): void
    {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $listing = Listing::factory()->create(['auction_id' => 7]);
        $listing->bidders()->attach([$winner->id => ['last_bid_price' => 300000], $loser->id => ['last_bid_price' => 250000]]);

        $closed = $this->engineAuction(7, [
            'current_price' => 300000,
            'remaining_time' => null,
            'status' => ['status' => 2, 'description' => ''],
            'bid_winner' => ['bidder_id' => (string) $winner->id, 'bid_price' => 300000, 'bid_time' => '2026-10-09 11:00:00'],
        ]);

        $sync = app(ListingSyncService::class);
        $sync->apply($listing, $closed);
        // The webhook, the scheduler and a page view may all see the close.
        $sync->apply($listing, $closed);
        $sync->apply(Listing::query()->find($listing->id), $closed);

        $this->assertSame(['sold'], $this->kinds($listing->user));
        $this->assertSame(['won'], $this->kinds($winner));
        $this->assertSame(['lost'], $this->kinds($loser));
    }

    public function test_an_auction_without_a_winner_tells_the_seller_it_can_be_relisted(): void
    {
        $bidder = User::factory()->create();
        $listing = Listing::factory()->create(['auction_id' => 7]);
        $listing->bidders()->attach($bidder->id, ['last_bid_price' => 1]);

        app(ListingSyncService::class)->apply($listing, $this->engineAuction(7, [
            'remaining_time' => null,
            'status' => ['status' => 5, 'description' => ''],
        ]));

        $this->assertSame(['unsold'], $this->kinds($listing->user));
        $this->assertSame(['lost'], $this->kinds($bidder));
    }

    public function test_notifications_are_also_emailed_and_open_their_target(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $listing = Listing::factory()->create();
        $user->notify(MarketplaceNotification::outbid($listing));

        Notification::assertSentTo($user, MarketplaceNotification::class, fn ($notification, array $channels) => $channels === ['database', 'mail']
            && str_contains($notification->toMail($user)->render()->toHtml(), $listing->title));
    }

    public function test_opening_a_notification_marks_it_read_and_redirects(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create();
        $user->notify(MarketplaceNotification::outbid($listing));
        $notification = $user->notifications()->sole();

        $this->actingAs(User::factory()->create())->get(route('notifications.show', $notification->id))->assertNotFound();

        $this->actingAs($user)->get(route('notifications.show', $notification->id))
            ->assertRedirect(route('listings.show', $listing, absolute: false));
        $this->assertNotNull($notification->refresh()->read_at);

        $user->notify(MarketplaceNotification::lost($listing));
        $this->actingAs($user)->post(route('notifications.read-all'));
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_the_bell_dropdown_loads_the_latest_notifications_only_when_asked(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create(['title' => 'Đồng hồ']);

        foreach (range(1, 10) as $i) {
            $user->notify(MarketplaceNotification::outbid($listing));
        }
        $newest = $user->notifications()->first();

        $this->actingAs($user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('unreadNotifications', 10)
            // Not part of an ordinary page load...
            ->missing('recentNotifications')
            // ...only of the partial reload the dropdown makes when it opens.
            ->reloadOnly(['recentNotifications', 'unreadNotifications'], fn (Assert $reload) => $reload
                ->has('recentNotifications', 8)
                ->where('recentNotifications.0.id', $newest->id)
                ->where('recentNotifications.0.read', false)
                ->where('recentNotifications.0.title', 'Bạn đã bị vượt giá')
                ->where('unreadNotifications', 10)));

        $this->get(route('home'))->assertOk();
    }

    public function test_only_seller_and_winner_see_each_others_contact_details(): void
    {
        $seller = User::factory()->create(['phone' => '0911111111']);
        $winner = User::factory()->create(['phone' => '0922222222', 'address' => 'Huế']);
        $listing = Listing::factory()->for($seller)->status(AuctionStatus::Completed)->create([
            'auction_id' => 7,
            'winner_id' => $winner->id,
            'winning_price' => 300000,
            'closed_notified_at' => now(),
        ]);

        Http::fake([
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, [
                'remaining_time' => null,
                'status' => ['status' => 2, 'description' => ''],
            ]))),
            'engine.test/api/v1/auction/7/bids' => Http::response($this->engineOk([])),
        ]);

        $this->actingAs($seller)->get(route('listings.show', $listing))->assertInertia(fn (Assert $page) => $page
            ->where('listing.contact.phone', '0922222222')
            ->where('listing.contact.address', 'Huế')
            ->where('listing.can.settle', true));

        $this->actingAs($winner)->get(route('listings.show', $listing))->assertInertia(fn (Assert $page) => $page
            ->where('listing.contact.phone', '0911111111')
            ->where('listing.is_winner', true));

        $this->actingAs(User::factory()->create())->get(route('listings.show', $listing))->assertInertia(fn (Assert $page) => $page
            ->where('listing.contact', null)
            ->where('listing.can.settle', false));

        $this->get(route('listings.show', $listing))->assertInertia(fn (Assert $page) => $page->where('listing.contact', null));
    }

    public function test_when_the_winner_backs_out_the_next_bidder_wins(): void
    {
        $winner = User::factory()->create();
        $successor = User::factory()->create();
        $listing = Listing::factory()->status(AuctionStatus::Completed)->create([
            'auction_id' => 7,
            'winner_id' => $winner->id,
            'winning_price' => 300000,
            'closed_notified_at' => now(),
        ]);
        Review::query()->create(['listing_id' => $listing->id, 'reviewer_id' => $winner->id, 'reviewee_id' => $listing->user_id, 'rating' => 1]);

        Http::fake([
            'engine.test/api/v1/bid/cancel-end-auction' => Http::response($this->engineOk([
                'auction_id' => 7,
                'has_bid_successor' => true,
                'bid' => ['bidder_id' => (string) $successor->id, 'bid_price' => 250000],
            ])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, [
                'remaining_time' => null,
                'status' => ['status' => 2, 'description' => ''],
            ]))),
            'engine.test/api/v1/auction/7/winner' => Http::response($this->engineOk([
                'auction_id' => 7,
                'bid_winner' => ['bid_id' => 3, 'bidder_id' => (string) $successor->id, 'bid_type' => '1', 'bid_price' => 250000, 'bid_time' => '2026-10-09 11:00:00'],
            ])),
        ]);

        $this->actingAs(User::factory()->create())->post(route('listings.withdraw-winner', $listing))->assertForbidden();

        $this->actingAs($winner)->post(route('listings.withdraw-winner', $listing))->assertRedirect();

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === 'http://engine.test/api/v1/bid/cancel-end-auction'
            && $request->data() === ['auction_id' => 7, 'bidder_id' => $winner->id, 'bid_price' => 300000]);

        $listing->refresh();
        $this->assertSame($successor->id, $listing->winner_id);
        $this->assertSame(250000, $listing->winning_price);
        $this->assertSame(['winner_withdrew'], $this->kinds($listing->user));
        $this->assertSame(['won'], $this->kinds($successor));
        $this->assertDatabaseCount('reviews', 0);

        // Backing out is on record, with a warning the first time.
        $this->assertSame(1, $winner->forfeits()->count());
        $this->assertSame($winner->id, $winner->forfeits()->sole()->declared_by);
        $this->assertSame(['forfeit_warning'], $this->kinds($winner));

        // The former winner is no longer a party to this auction.
        $this->actingAs($winner)->post(route('listings.withdraw-winner', $listing))->assertForbidden();
    }

    public function test_giving_up_results_repeatedly_earns_a_growing_ban_from_bidding(): void
    {
        config(['marketplace.forfeit_bans' => [2 => 7, 3 => 30], 'marketplace.forfeit_window_days' => 90]);

        $winner = User::factory()->create();
        $penalty = app(ForfeitPenalty::class);
        $listing = fn () => Listing::factory()->status(AuctionStatus::Completed)->create();

        // First time: a warning that says what comes next.
        $this->assertNull($penalty->apply($winner, $listing(), $winner));
        $this->assertFalse($winner->refresh()->isBidBanned());
        $warning = $winner->notifications()->sole();
        $this->assertSame('forfeit_warning', $warning->data['kind']);
        $this->assertStringContainsString('7 ngày', $warning->data['body']);

        // Second: 7 days. Third: 30 days.
        $until = $penalty->apply($winner, $listing(), $winner);
        $this->assertEqualsWithDelta(7 * 86400, now()->diffInSeconds($until), 60);
        $this->assertTrue($winner->refresh()->isBidBanned());

        $until = $penalty->apply($winner, $listing(), null);
        $this->assertEqualsWithDelta(30 * 86400, now()->diffInSeconds($until), 60);
        $this->assertSame(3, $winner->forfeits()->count());
        $this->assertSame(['bid_ban', 'bid_ban', 'forfeit_warning'], $winner->notifications()->latest()->get()->map(fn ($n) => $n->data['kind'])->sort()->values()->all());
    }

    public function test_old_forfeits_stop_counting_and_a_ban_is_never_shortened(): void
    {
        config(['marketplace.forfeit_bans' => [2 => 7, 3 => 30], 'marketplace.forfeit_window_days' => 90]);

        $winner = User::factory()->create();
        $penalty = app(ForfeitPenalty::class);
        $listing = fn () => Listing::factory()->status(AuctionStatus::Completed)->create();

        $penalty->apply($winner, $listing(), $winner);
        $this->travel(100)->days();

        // The earlier one is outside the window: this is a "first time" again.
        $this->assertNull($penalty->apply($winner, $listing(), $winner));
        $this->assertSame(1, $penalty->recentCount($winner));

        // An admin-set or longer ban is kept when a shorter one would apply.
        $winner->forceFill(['bid_banned_until' => now()->addYear()])->save();
        $until = $penalty->apply($winner, $listing(), $winner);
        $this->assertTrue($until->greaterThan(now()->addMonths(11)));
    }

    public function test_a_banned_member_cannot_bid_and_the_ban_ends_by_itself(): void
    {
        $listing = Listing::factory()->create(['auction_id' => 7]);
        $member = User::factory()->create();
        $member->forceFill(['bid_banned_until' => now()->addDays(3)])->save();

        Http::fake([
            'engine.test/api/v1/bid' => Http::response($this->engineOk(['auction_id' => 7])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7))),
        ]);

        $this->actingAs($member)->post(route('listings.bids.store', $listing), ['bid_type' => 2])
            ->assertSessionHasErrors('bid_price');
        Http::assertNothingSent();

        $this->travel(4)->days();

        $this->actingAs($member->refresh())->post(route('listings.bids.store', $listing), ['bid_type' => 2])
            ->assertSessionHasNoErrors();
    }

    public function test_with_no_successor_the_auction_is_cancelled(): void
    {
        $winner = User::factory()->create();
        $listing = Listing::factory()->status(AuctionStatus::Completed)->create([
            'auction_id' => 7, 'winner_id' => $winner->id, 'winning_price' => 300000, 'closed_notified_at' => now(),
        ]);

        Http::fake([
            'engine.test/api/v1/bid/cancel-end-auction' => Http::response($this->engineOk(['auction_id' => 7, 'has_bid_successor' => false])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, [
                'remaining_time' => null,
                'status' => ['status' => 3, 'description' => ''],
            ]))),
        ]);

        // The seller may also declare that the winner did not buy.
        $this->actingAs($listing->user)->post(route('listings.withdraw-winner', $listing))->assertRedirect();

        $listing->refresh();
        $this->assertSame(AuctionStatus::CancelledBySeller, $listing->auction_status);
        $this->assertNull($listing->winner_id);
        $this->assertTrue($listing->user->can('relist', $listing));
    }

    public function test_seller_and_winner_review_each_other_once(): void
    {
        $winner = User::factory()->create();
        $listing = Listing::factory()->status(AuctionStatus::Completed)->create(['winner_id' => $winner->id, 'winning_price' => 1]);

        $this->actingAs($winner)->post(route('listings.reviews.store', $listing), ['rating' => 5, 'comment' => 'Tốt']);
        $this->actingAs($winner)->post(route('listings.reviews.store', $listing), ['rating' => 4]);
        $this->actingAs($listing->user)->post(route('listings.reviews.store', $listing), ['rating' => 3]);

        $this->assertDatabaseCount('reviews', 2);
        $this->assertSame(4.0, (float) $listing->user->reviewsReceived()->avg('rating'));
        $this->assertSame(3, $winner->reviewsReceived()->sole()->rating);

        $this->actingAs(User::factory()->create())->post(route('listings.reviews.store', $listing), ['rating' => 5])->assertForbidden();
        $this->actingAs($winner)->post(route('listings.reviews.store', $listing), ['rating' => 9])->assertSessionHasErrors('rating');

        $this->get(route('users.show', $listing->user))->assertInertia(fn (Assert $page) => $page
            ->where('member.rating', 4)
            ->where('member.review_count', 1)
            ->has('reviews', 1));
    }

    public function test_a_bidder_can_withdraw_a_bid_while_the_auction_runs(): void
    {
        $bidder = User::factory()->create();
        $listing = Listing::factory()->create(['auction_id' => 7]);
        $listing->bidders()->attach($bidder->id, ['last_bid_price' => 200000]);

        Http::fake([
            'engine.test/api/v1/bid/cancel-in-auction' => Http::response($this->engineOk(['auction_id' => 7])),
            'engine.test/api/v1/auction/7' => Http::response($this->engineOk($this->engineAuction(7, ['current_price' => 150000, 'bid_number' => 2]))),
            'engine.test/api/v1/auction/7/bids' => Http::response($this->engineOk([
                ['bid_id' => 1, 'bid_time' => '2026-10-09 10:00:00', 'bid_type' => '1', 'bid_price' => 150000, 'bidder_id' => (string) $bidder->id, 'current_price' => 150000, 'is_cancel' => false],
                ['bid_id' => 2, 'bid_time' => '2026-10-09 10:05:00', 'bid_type' => '1', 'bid_price' => 200000, 'bidder_id' => (string) $bidder->id, 'current_price' => 200000, 'is_cancel' => true],
            ])),
        ]);

        $this->actingAs($bidder)->delete(route('listings.bids.destroy', $listing), ['bid_price' => 200000])->assertRedirect();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://engine.test/api/v1/bid/cancel-in-auction'
            && $request->data() === ['auction_id' => 7, 'bidder_id' => $bidder->id, 'bid_price' => 200000]);

        $this->assertSame(150000, $listing->refresh()->current_price);
        $this->assertSame(150000, (int) $bidder->bidListings()->sole()->pivot->last_bid_price);

        $this->actingAs($bidder)->get(route('listings.bids.index', $listing))->assertInertia(fn (Assert $page) => $page
            ->has('bids', 1)
            ->where('bids.0.mine', true)
            ->where('bids.0.can_cancel', true));
    }

    public function test_following_a_listing_toggles_and_shows_on_the_dashboard(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create();

        $this->post(route('listings.watch', $listing))->assertRedirect(route('login'));

        $this->actingAs($user)->post(route('listings.watch', $listing));
        $this->assertTrue($listing->watchers()->whereKey($user->id)->exists());
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('watching', 1));

        $this->actingAs($user)->post(route('listings.watch', $listing));
        $this->assertFalse($listing->watchers()->whereKey($user->id)->exists());
    }
}

<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Enums\SaleType;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\Setting;
use App\Models\User;
use App\Services\ListingModeration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A listing does not need an auction: "fixed price" is a plain post with a
 * price, and the auction engine is never involved.
 */
class FixedPriceListingTest extends TestCase
{
    use EngineFake, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
        Storage::fake('public');

        // Any call to the engine in these tests is a bug.
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
            'title' => 'Bàn làm việc',
            'category_id' => Category::factory()->create()->id,
            'city_id' => City::query()->create(['name' => 'Hà Nội'])->id,
            'condition' => 'used',
            'images' => [UploadedFile::fake()->image('a.jpg')],
            'start_price' => 800000,
            ...$overrides,
        ];
    }

    public function test_a_listing_can_be_posted_without_an_auction(): void
    {
        $seller = User::factory()->create();

        // Leftover auction fields from the form are ignored, valid or not.
        $response = $this->actingAs($seller)->post(route('listings.store'), $this->payload([
            'auction_type' => 1,
            'end_time' => 'not a date',
            'bid_step' => 5000,
        ]));

        $listing = Listing::query()->sole();
        $response->assertRedirect(route('listings.show', $listing));

        $this->assertSame(SaleType::Fixed, $listing->sale_type);
        $this->assertNull($listing->auction_id);
        $this->assertNull($listing->auction_type);
        $this->assertNull($listing->bid_step);
        $this->assertSame(800000, $listing->current_price);
        $this->assertSame(AuctionStatus::Running, $listing->auction_status);
        $this->assertTrue($listing->isPublished());

        Http::assertNothingSent();
    }

    public function test_the_price_is_required_and_the_sale_type_must_be_known(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->post(route('listings.store'), $this->payload(['start_price' => null]))
            ->assertSessionHasErrors('start_price');
        $this->actingAs($seller)->post(route('listings.store'), $this->payload(['sale_type' => 'raffle']))
            ->assertSessionHasErrors('sale_type');

        $this->assertDatabaseCount('listings', 0);
    }

    public function test_it_is_listed_and_shown_without_calling_the_engine(): void
    {
        $listing = Listing::factory()->fixedPrice()->create(['title' => 'Bàn làm việc', 'current_price' => 800000]);
        Listing::factory()->create(['title' => 'Phiên đấu giá']);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('running.data', 2));

        // Type 3 in the search filter means "fixed price".
        $this->get(route('listings.index', ['type' => 3]))->assertInertia(fn (Assert $page) => $page
            ->has('listings.data', 1)
            ->where('listings.data.0.sale_type', 'fixed')
            ->where('listings.data.0.status_label', 'Đang bán'));

        $this->get(route('listings.show', $listing))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('listings/Show')
            ->where('auction', null)
            ->has('bids', 0)
            ->where('listing.sale_type', 'fixed')
            ->where('listing.auction_type', null)
            ->where('listing.current_price', 800000));

        Http::assertNothingSent();
    }

    public function test_the_sellers_contact_is_shown_to_verified_members_only(): void
    {
        $seller = User::factory()->create(['phone' => '0911111111']);
        $listing = Listing::factory()->fixedPrice()->for($seller)->create();

        $this->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page->where('listing.contact', null));

        $this->actingAs(User::factory()->unverified()->create())->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page->where('listing.contact', null));

        $this->actingAs(User::factory()->create())->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page
                ->where('listing.contact.phone', '0911111111')
                ->where('listing.contact.email', $seller->email));

        // The seller sees the controls instead.
        $this->actingAs($seller)->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page
                ->where('listing.contact', null)
                ->where('listing.can.mark_sold', true)
                ->where('listing.can.cancel', true));
    }

    public function test_the_seller_marks_it_sold_or_takes_it_down(): void
    {
        $sold = Listing::factory()->fixedPrice()->create();
        $withdrawn = Listing::factory()->fixedPrice()->for($sold->user)->create();

        $this->actingAs(User::factory()->create())->post(route('listings.sold', $sold))->assertForbidden();

        $this->actingAs($sold->user)->post(route('listings.sold', $sold))->assertRedirect();
        $this->actingAs($sold->user)->delete(route('listings.destroy', $withdrawn))->assertRedirect();

        $this->assertSame('Đã bán', $sold->refresh()->statusLabel());
        $this->assertSame('Đã gỡ', $withdrawn->refresh()->statusLabel());

        // Sold: the contact is no longer handed out, and it cannot be closed twice.
        $this->actingAs(User::factory()->create())->get(route('listings.show', $sold))
            ->assertInertia(fn (Assert $page) => $page->where('listing.contact', null));
        $this->actingAs($sold->user)->post(route('listings.sold', $sold))->assertForbidden();

        // A listing that was taken down can be put up again; a sold one cannot.
        $this->assertTrue($sold->user->can('relist', $withdrawn));
        $this->assertFalse($sold->user->can('relist', $sold));

        $this->actingAs($sold->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('selling', 0)
            ->has('ended', 2));

        Http::assertNothingSent();
    }

    public function test_a_listing_can_go_without_a_price(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->post(route('listings.store'), $this->payload([
            'is_negotiable' => true,
            'start_price' => null,
        ]))->assertSessionHasNoErrors();

        $listing = Listing::query()->sole();
        $this->assertTrue($listing->is_negotiable);
        $this->assertSame(0, $listing->current_price);

        $this->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page->where('listing.negotiable', true));

        // An auction always needs its start price, whatever the checkbox says.
        $this->actingAs($seller)->post(route('listings.store'), $this->payload([
            'sale_type' => 'auction',
            'auction_type' => 1,
            'end_time' => now()->addDay()->format('Y-m-d\TH:i'),
            'is_negotiable' => true,
            'start_price' => null,
        ]))->assertSessionHasErrors('start_price');
    }

    public function test_price_sorting_puts_listings_without_a_price_last(): void
    {
        Listing::factory()->fixedPrice()->create(['title' => 'Thoả thuận', 'is_negotiable' => true, 'current_price' => 0]);
        Listing::factory()->fixedPrice()->create(['title' => 'Rẻ', 'current_price' => 1000]);
        Listing::factory()->fixedPrice()->create(['title' => 'Đắt', 'current_price' => 9000]);

        $titles = fn (string $sort) => collect($this->get(route('listings.index', ['sort' => $sort]))->inertiaProps('listings.data'))->pluck('title')->all();

        $this->assertSame(['Rẻ', 'Đắt', 'Thoả thuận'], $titles('price_asc'));
        $this->assertSame(['Đắt', 'Rẻ', 'Thoả thuận'], $titles('price_desc'));
    }

    public function test_a_fixed_price_listing_expires_after_its_lifetime(): void
    {
        config(['marketplace.fixed_listing_days' => 30]);

        $seller = User::factory()->create();
        $this->actingAs($seller)->post(route('listings.store'), $this->payload());

        $listing = Listing::query()->sole();
        $this->assertEqualsWithDelta(30 * 86400, now()->diffInSeconds($listing->ends_at), 60);

        // Not yet.
        $this->artisan('listings:expire')->assertSuccessful();
        $this->assertSame(AuctionStatus::Running, $listing->refresh()->auction_status);

        $this->travel(31)->days();
        $this->artisan('listings:expire')->assertSuccessful();
        $this->artisan('listings:expire')->assertSuccessful();

        $listing->refresh();
        $this->assertSame(AuctionStatus::Expired, $listing->auction_status);
        $this->assertSame('Hết hạn', $listing->statusLabel());
        // Told once, and able to put it up again.
        $this->assertSame(['expired'], $seller->notifications()->get()->map(fn ($n) => $n->data['kind'])->all());
        $this->assertTrue($seller->can('relist', $listing));

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('running.data', 0));
    }

    public function test_expiry_leaves_auctions_and_closed_listings_alone(): void
    {
        $auction = Listing::factory()->create(['auction_id' => 7, 'ends_at' => now()->subDay()]);
        $sold = Listing::factory()->fixedPrice()->status(AuctionStatus::Completed)->create(['ends_at' => now()->subDay()]);

        $this->artisan('listings:expire')->assertSuccessful();

        $this->assertSame(AuctionStatus::Running, $auction->refresh()->auction_status);
        $this->assertSame(AuctionStatus::Completed, $sold->refresh()->auction_status);
    }

    public function test_only_auctions_can_be_marked_sold_by_hand(): void
    {
        $auction = Listing::factory()->create(['auction_id' => 7]);

        $this->actingAs($auction->user)->post(route('listings.sold', $auction))->assertForbidden();
    }

    public function test_nobody_can_bid_on_it(): void
    {
        $listing = Listing::factory()->fixedPrice()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('listings.bids.store', $listing), ['bid_type' => 2])->assertNotFound();
        $this->actingAs($user)->delete(route('listings.bids.destroy', $listing), ['bid_price' => 1])->assertNotFound();
        $this->get(route('listings.bids.index', $listing))->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_the_sync_command_leaves_it_alone(): void
    {
        Listing::factory()->fixedPrice()->create();

        $this->artisan('auctions:sync')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_with_approval_on_it_waits_and_approving_needs_no_engine(): void
    {
        Setting::write(Setting::LISTING_APPROVAL, true);

        $seller = User::factory()->create();
        $this->actingAs($seller)->post(route('listings.store'), $this->payload())->assertRedirect(route('dashboard'));

        $listing = Listing::query()->sole();
        $this->assertSame(ModerationStatus::Pending, $listing->moderation_status);

        // Hidden from everyone else; its seller can already look at it.
        $this->assertFalse(Gate::forUser(null)->allows('view', $listing));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('view', $listing));
        $this->get(route('listings.show', $listing))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('listing.moderation', 'pending'));

        app(ListingModeration::class)->approve($listing);

        $this->assertTrue($listing->refresh()->isPublished());
        $this->get(route('listings.show', $listing))->assertOk();

        Http::assertNothingSent();
    }

    public function test_it_can_be_followed_commented_on_and_reported(): void
    {
        $listing = Listing::factory()->fixedPrice()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('listings.watch', $listing))->assertRedirect();
        $this->actingAs($user)->post(route('listings.comments.store', $listing), ['body' => 'Còn không?'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('listings.reports.store', $listing), ['reason' => 'scam'])->assertSessionHasNoErrors();

        $this->assertSame(1, $listing->watchers()->count());
        $this->assertSame(1, $listing->comments()->count());
        $this->assertSame(1, $listing->reports()->count());
    }
}

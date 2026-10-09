<?php

namespace Tests\Feature\Marketplace;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\Setting;
use App\Models\User;
use App\Services\ListingModeration;
use App\Services\SavedSearchMatcher;
use App\Support\MarketplaceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ListingToolsTest extends TestCase
{
    use EngineFake, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEngine();
        Storage::fake('public');
        Http::fake();
    }

    private function kinds(User $user): array
    {
        return $user->notifications()->get()->map(fn ($notification) => $notification->data['kind'])->all();
    }

    // ---- renewing --------------------------------------------------------

    public function test_the_seller_is_reminded_once_before_a_listing_expires_and_can_renew_it(): void
    {
        MarketplaceSettings::save(['fixed_listing_days' => 30, 'expiry_reminder_days' => 3]);

        $listing = Listing::factory()->fixedPrice()->create(['ends_at' => now()->addDays(10)]);

        $this->artisan('listings:expire');
        $this->assertSame([], $this->kinds($listing->user));

        $this->travel(8)->days();
        $this->artisan('listings:expire');
        $this->artisan('listings:expire');
        $this->assertSame(['expiring'], $this->kinds($listing->user));

        $this->actingAs($listing->user)->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page->where('listing.can.renew', true));

        $this->actingAs($listing->user)->post(route('listings.renew', $listing))->assertRedirect();

        $listing->refresh();
        $this->assertEqualsWithDelta(30 * 86400, now()->diffInSeconds($listing->ends_at), 60);
        // A new lifetime gets its own reminder later on.
        $this->assertNull($listing->expiry_reminded_at);
    }

    public function test_an_expired_listing_comes_back_when_renewed(): void
    {
        $listing = Listing::factory()->fixedPrice()->create(['ends_at' => now()->subHour()]);

        $this->artisan('listings:expire');
        $this->assertSame(AuctionStatus::Expired, $listing->refresh()->auction_status);

        $this->actingAs($listing->user)->post(route('listings.renew', $listing))->assertRedirect();

        $listing->refresh();
        $this->assertSame(AuctionStatus::Running, $listing->auction_status);
        $this->assertTrue($listing->ends_at->isFuture());
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('running.data', 1));
    }

    public function test_only_the_seller_of_a_renewable_fixed_price_listing_can_renew(): void
    {
        $fixed = Listing::factory()->fixedPrice()->create();
        $sold = Listing::factory()->fixedPrice()->status(AuctionStatus::Completed)->create();
        $auction = Listing::factory()->create(['auction_id' => 7]);

        $this->actingAs(User::factory()->create())->post(route('listings.renew', $fixed))->assertForbidden();
        $this->actingAs($sold->user)->post(route('listings.renew', $sold))->assertForbidden();
        $this->actingAs($auction->user)->post(route('listings.renew', $auction))->assertForbidden();
    }

    public function test_reminders_can_be_turned_off(): void
    {
        MarketplaceSettings::save(['expiry_reminder_days' => 0]);

        $listing = Listing::factory()->fixedPrice()->create(['ends_at' => now()->addHour()]);

        $this->artisan('listings:expire');

        $this->assertSame([], $this->kinds($listing->user));
    }

    // ---- image order -----------------------------------------------------

    public function test_the_seller_reorders_images_and_the_first_becomes_the_cover(): void
    {
        $listing = Listing::factory()->fixedPrice()->create([
            'category_id' => Category::factory()->create()->id,
            'city_id' => City::query()->create(['name' => 'Hà Nội'])->id,
        ]);
        [$a, $b, $c] = collect(['a', 'b', 'c'])->map(fn (string $name, int $index) => $listing->images()->create([
            'path' => UploadedFile::fake()->image("{$name}.jpg")->store("listings/{$listing->id}", 'public'),
            'position' => $index,
        ]))->all();

        $this->actingAs($listing->user)->put(route('listings.update', $listing), [
            'title' => $listing->title,
            'category_id' => $listing->category_id,
            'city_id' => $listing->city_id,
            'condition' => 'used',
            'remove_images' => [$b->id],
            // c first, then a; an id from elsewhere is ignored.
            'image_order' => [$c->id, 999999, $a->id],
            'images' => [UploadedFile::fake()->image('new.jpg')],
        ])->assertSessionHasNoErrors();

        $images = $listing->images()->get();
        $this->assertSame([$c->id, $a->id], $images->take(2)->pluck('id')->all());
        $this->assertCount(3, $images);
        // New uploads go after the ones that were kept.
        $this->assertSame($images->last()->id, $images->max('id'));

        $this->get(route('listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page->where('listing.image', $c->url)->where('listing.images.0', $c->url));
    }

    // ---- saved searches --------------------------------------------------

    public function test_a_member_saves_a_search_once_and_needs_at_least_one_criterion(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)->post(route('saved-searches.store'), [])->assertSessionHasErrors('q');

        $this->actingAs($user)->post(route('saved-searches.store'), ['q' => ' xe đạp ', 'category_id' => $category->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('saved-searches.store'), ['q' => 'xe đạp', 'category_id' => $category->id])->assertSessionHasNoErrors();

        $search = SavedSearch::query()->sole();
        $this->assertSame('xe đạp', $search->q);

        // The search page knows it is saved, and offers saving only when there is something to save.
        $this->actingAs($user)->get(route('categories.show', [$category, 'q' => 'xe đạp']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('savedSearch.id', $search->id)->where('savedSearch.can_save', true));
        $this->actingAs($user)->get(route('listings.index'))
            ->assertInertia(fn (Assert $page) => $page->where('savedSearch.id', null)->where('savedSearch.can_save', false));

        $this->actingAs($user)->get(route('saved-searches.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('saved-searches/Index')
            ->has('searches', 1)
            ->where('searches.0.label', '"xe đạp" · '.$category->name));

        $this->actingAs(User::factory()->create())->delete(route('saved-searches.destroy', $search))->assertForbidden();
        $this->actingAs($user)->delete(route('saved-searches.destroy', $search))->assertRedirect();
        $this->assertDatabaseCount('saved_searches', 0);
    }

    public function test_guests_are_not_offered_saved_searches(): void
    {
        $this->get(route('listings.index', ['q' => 'xe']))->assertInertia(fn (Assert $page) => $page->where('savedSearch', null));
        $this->post(route('saved-searches.store'), ['q' => 'xe'])->assertRedirect(route('login'));
    }

    public function test_a_new_listing_notifies_the_members_whose_saved_search_it_matches(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $hanoi = City::query()->create(['name' => 'Hà Nội']);
        $hue = City::query()->create(['name' => 'Huế']);
        $seller = User::factory()->create();

        $save = fn (array $criteria) => tap(User::factory()->create(), fn (User $user) => $user->savedSearches()->create($criteria));

        $byKeyword = $save(['q' => 'xe dap']);           // typed without accents
        $byParentCategory = $save(['category_id' => $parent->id]);
        $byCityAndType = $save(['city_id' => $hanoi->id, 'type' => 3]);
        $wrongCity = $save(['city_id' => $hue->id]);
        $wrongType = $save(['type' => 1]);
        $wrongKeyword = $save(['q' => 'tủ lạnh']);
        $seller->savedSearches()->create(['q' => 'xe']);  // never told about their own listing
        // Two matching searches of one member: still a single notice.
        $byKeyword->savedSearches()->create(['category_id' => $child->id]);

        $listing = Listing::factory()->fixedPrice()->for($seller)->create([
            'title' => 'Xe Đạp Nhật cũ',
            'category_id' => $child->id,
            'city_id' => $hanoi->id,
        ]);

        $this->assertSame(3, app(SavedSearchMatcher::class)->listingPublished($listing));

        foreach ([$byKeyword, $byParentCategory, $byCityAndType] as $user) {
            $notification = $user->notifications()->sole();
            $this->assertSame('saved_search', $notification->data['kind']);
            $this->assertSame(route('listings.show', $listing, absolute: false), $notification->data['url']);
        }
        $this->assertStringContainsString('"xe dap"', $byKeyword->notifications()->sole()->data['body']);

        foreach ([$wrongCity, $wrongType, $wrongKeyword, $seller] as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
    }

    public function test_posting_and_first_approval_trigger_the_saved_search_notices(): void
    {
        $watcher = User::factory()->create();
        $watcher->savedSearches()->create(['q' => 'bàn']);

        $payload = fn (string $title) => [
            'sale_type' => 'fixed',
            'title' => $title,
            'category_id' => Category::factory()->create()->id,
            'city_id' => City::query()->create(['name' => 'Hà Nội'])->id,
            'condition' => 'used',
            'images' => [UploadedFile::fake()->image('a.jpg')],
            'start_price' => 800000,
        ];

        // Posted and public straight away.
        $this->actingAs(User::factory()->create())->post(route('listings.store'), $payload('Bàn gỗ'))->assertSessionHasNoErrors();
        $this->assertSame(1, $watcher->notifications()->count());

        // With moderation on: nothing while it waits, a notice when it is first approved,
        // and none when a hidden listing is merely shown again.
        Setting::write(Setting::LISTING_APPROVAL, true);
        $this->actingAs(User::factory()->create())->post(route('listings.store'), $payload('Bàn học'));
        $pending = Listing::query()->where('moderation_status', ModerationStatus::Pending)->sole();
        $this->assertSame(1, $watcher->notifications()->count());

        app(ListingModeration::class)->approve($pending);
        $this->assertSame(2, $watcher->notifications()->count());

        app(ListingModeration::class)->hide($pending, 'Tạm ẩn');
        app(ListingModeration::class)->approve($pending->refresh());
        $this->assertSame(2, $watcher->notifications()->count());

        Http::assertNothingSent();
    }

    public function test_a_member_can_keep_only_a_limited_number_of_saved_searches(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 20) as $i) {
            $user->savedSearches()->create(['q' => "từ khoá {$i}"]);
        }

        $this->actingAs($user)->post(route('saved-searches.store'), ['q' => 'thêm nữa'])->assertSessionHasErrors('q');
        $this->assertSame(20, $user->savedSearches()->count());
    }
}

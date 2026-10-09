<?php

namespace Tests\Feature\Admin;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Filament\Pages\MarketplaceSettingsPage;
use App\Filament\Resources\Comments\Pages\ManageComments;
use App\Filament\Resources\Listings\Pages\ManageListings;
use App\Filament\Resources\Reports\Pages\ManageReports;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Widgets\ActivityChart;
use App\Filament\Widgets\RevenueChart;
use App\Models\AdminAction;
use App\Models\Comment;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\Setting;
use App\Models\User;
use App\Services\ForfeitPenalty;
use App\Support\DailySeries;
use App\Support\MarketplaceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class AdminToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_default_to_the_config_file_and_can_be_overridden(): void
    {
        config(['marketplace.fixed_listing_days' => 30, 'marketplace.forfeit_bans' => [2 => 7, 3 => 30]]);

        $this->assertSame(30, MarketplaceSettings::fixedListingDays());
        $this->assertSame([2 => 7, 3 => 30], MarketplaceSettings::forfeitBans());

        MarketplaceSettings::save(['fixed_listing_days' => 45, 'forfeit_ban_second_days' => 0, 'forfeit_ban_repeat_days' => 14, 'unknown' => 1]);

        $this->assertSame(45, MarketplaceSettings::fixedListingDays());
        // A step set to 0 days is skipped: the second forfeit is then only a warning too.
        $this->assertSame([3 => 14], MarketplaceSettings::forfeitBans());
    }

    public function test_an_admin_changes_settings_on_the_settings_page_and_it_is_logged(): void
    {
        config(['marketplace.contact_email' => 'macdinh@example.com']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Tin giá cố định hiển thị')->assertSee('30 ngày')->assertSee('macdinh@example.com');
        $this->actingAs(User::factory()->moderator()->create())->get('/admin/settings')->assertForbidden();

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('edit', [
                'fixed_listing_days' => 60,
                'expiry_reminder_days' => 5,
                'listing_approval' => true,
                'forfeit_window_days' => 90,
                'forfeit_ban_second_days' => 7,
                'forfeit_ban_repeat_days' => 30,
                'min_length' => 10,
                'mixed_case' => false,
                'numbers' => true,
                'symbols' => false,
                'contact_email' => 'hotro@chotgia.test',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('hotro@chotgia.test', MarketplaceSettings::contactEmail());
        $this->assertSame(60, MarketplaceSettings::fixedListingDays());
        $this->assertSame(5, MarketplaceSettings::expiryReminderDays());

        $log = AdminAction::query()->sole();
        $this->assertSame('settings.updated', $log->action);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringContainsString('Tin giá cố định hiển thị: 30 ngày → 60 ngày', $log->summary);
        $this->assertStringContainsString('Duyệt tin trước khi hiển thị: Tắt → Bật', $log->summary);
        $this->assertStringNotContainsString('Tính các lần từ bỏ', $log->summary);
        $this->assertStringContainsString('Email liên hệ: macdinh@example.com → hotro@chotgia.test', $log->summary);

        // The terms page shows the new address.
        $this->get(route('legal.terms'))->assertInertia(fn (Assert $page) => $page->where('contactEmail', 'hotro@chotgia.test'));

        // The new values are in force.
        $this->post('/logout');
        $this->post(route('register.store'), ['name' => 'A', 'email' => 'a@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('password');
    }

    public function test_the_contact_email_falls_back_to_the_default_and_must_be_an_address(): void
    {
        config(['marketplace.contact_email' => 'macdinh@example.com']);
        MarketplaceSettings::saveContactEmail('hotro@chotgia.test');

        $settings = [
            ...MarketplaceSettings::all(),
            'listing_approval' => false,
            ...Setting::passwordPolicy(),
        ];

        Livewire::actingAs(User::factory()->admin()->create())->test(MarketplaceSettingsPage::class)
            ->callAction('edit', [...$settings, 'contact_email' => 'không phải email'])
            ->assertHasActionErrors(['contact_email']);
        $this->assertSame('hotro@chotgia.test', MarketplaceSettings::contactEmail());

        // Cleared: back to the address from the environment.
        Livewire::actingAs(User::factory()->admin()->create())->test(MarketplaceSettingsPage::class)
            ->callAction('edit', [...$settings, 'contact_email' => ''])
            ->assertHasNoActionErrors();
        $this->assertSame('macdinh@example.com', MarketplaceSettings::contactEmail());
    }

    public function test_the_forfeit_penalty_follows_the_admin_settings(): void
    {
        MarketplaceSettings::save(['forfeit_ban_second_days' => 2, 'forfeit_ban_repeat_days' => 10]);

        $winner = User::factory()->create();
        $penalty = app(ForfeitPenalty::class);

        $penalty->apply($winner, Listing::factory()->create(), $winner);
        $until = $penalty->apply($winner, Listing::factory()->create(), $winner);

        $this->assertEqualsWithDelta(2 * 86400, now()->diffInSeconds($until), 60);
    }

    public function test_several_listings_can_be_approved_or_hidden_at_once(): void
    {
        Http::fake();

        $moderator = User::factory()->moderator()->create();
        $pending = Listing::factory()->fixedPrice()->count(2)->create(['moderation_status' => ModerationStatus::Pending]);
        $expired = Listing::factory()->create(['auction_id' => null, 'moderation_status' => ModerationStatus::Pending, 'end_time' => now()->addMinute()]);
        $live = Listing::factory()->fixedPrice()->count(2)->create();

        Livewire::actingAs($moderator)->test(ManageListings::class)
            ->callTableBulkAction('approveSelected', [...$pending, $expired]);

        $this->assertTrue($pending->every(fn (Listing $listing) => $listing->refresh()->isPublished()));
        // The one whose end time has all but passed is refused; the rest still go through.
        $this->assertSame(ModerationStatus::Pending, $expired->refresh()->moderation_status);

        Livewire::actingAs($moderator)->test(ManageListings::class)
            ->callTableBulkAction('hideSelected', $live, ['note' => 'Trùng lặp']);

        $this->assertTrue($live->every(fn (Listing $listing) => $listing->refresh()->moderation_status === ModerationStatus::Hidden));
        $this->assertSame('Trùng lặp', $live->first()->moderation_note);

        // Each listing has its own line in the audit log.
        $this->assertSame(2, AdminAction::query()->where('action', 'listing.approved')->count());
        $this->assertSame(2, AdminAction::query()->where('action', 'listing.hidden')->count());
        $this->assertSame(2, $live->first()->user->notifications()->count() + $live->last()->user->notifications()->count());
    }

    public function test_accounts_reports_and_comments_have_bulk_actions_too(): void
    {
        $admin = User::factory()->admin()->create();
        $members = User::factory()->count(3)->create();

        Livewire::actingAs($admin)->test(ManageUsers::class)
            // Selecting yourself along with others must not lock you out.
            ->callTableBulkAction('blockSelected', [...$members, $admin], ['block_reason' => 'Spam']);

        $this->assertTrue($members->every(fn (User $user) => $user->refresh()->isBlocked()));
        $this->assertFalse($admin->refresh()->isBlocked());

        Livewire::actingAs($admin)->test(ManageUsers::class)->callTableBulkAction('unblockSelected', $members);
        $this->assertTrue($members->every(fn (User $user) => ! $user->refresh()->isBlocked()));

        $listing = Listing::factory()->create();
        $reports = $members->map(fn (User $user) => ListingReport::query()->create(['listing_id' => $listing->id, 'user_id' => $user->id, 'reason' => 'scam']));
        Livewire::actingAs($admin)->test(ManageReports::class)->callTableBulkAction('dismissSelected', $reports);
        $this->assertSame(0, ListingReport::query()->whereNull('handled_at')->count());

        $comments = $members->map(fn (User $user) => Comment::query()->create(['listing_id' => $listing->id, 'user_id' => $user->id, 'body' => 'Quảng cáo']));
        Livewire::actingAs($admin)->test(ManageComments::class)->callTableBulkAction('deleteSelected', $comments);
        $this->assertDatabaseCount('comments', 0);

        $this->assertSame(
            ['user.bulk_blocked', 'user.bulk_unblocked', 'report.bulk_dismissed', 'comment.bulk_deleted'],
            AdminAction::query()->oldest('id')->pluck('action')->all(),
        );
        $this->assertStringContainsString('Khoá 3 tài khoản', AdminAction::query()->oldest('id')->first()->summary);
    }

    public function test_daily_series_count_and_sum_per_day_in_order(): void
    {
        $this->travelTo('2026-10-10 12:00:00');

        Listing::factory()->status(AuctionStatus::Completed)->create(['ends_at' => '2026-10-10 09:00:00', 'winning_price' => 100]);
        Listing::factory()->status(AuctionStatus::Completed)->create(['ends_at' => '2026-10-10 23:30:00', 'winning_price' => 50]);
        Listing::factory()->status(AuctionStatus::Completed)->create(['ends_at' => '2026-10-08 10:00:00', 'winning_price' => 7]);
        // Outside the range.
        Listing::factory()->status(AuctionStatus::Completed)->create(['ends_at' => '2026-10-01 10:00:00', 'winning_price' => 999]);

        $days = DailySeries::days(3);

        $this->assertSame(['2026-10-08', '2026-10-09', '2026-10-10'], $days);
        $this->assertSame(['08/10', '09/10', '10/10'], DailySeries::labels($days));
        $this->assertSame([1, 0, 2], DailySeries::count(Listing::query(), 'ends_at', $days));
        $this->assertSame([7, 0, 150], DailySeries::sum(Listing::query(), 'ends_at', 'winning_price', $days));
    }

    public function test_the_dashboard_charts_render(): void
    {
        $admin = User::factory()->admin()->create();
        Listing::factory()->status(AuctionStatus::Completed)->create(['ends_at' => now(), 'winning_price' => 250000]);

        Livewire::actingAs($admin)->test(ActivityChart::class)->assertOk()->assertSee('Hoạt động theo ngày');
        Livewire::actingAs($admin)->test(RevenueChart::class)->assertOk()->assertSee('Doanh số đấu giá theo ngày')
            ->set('filter', '7d')->assertOk();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}

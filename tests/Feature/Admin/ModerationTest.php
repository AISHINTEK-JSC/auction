<?php

namespace Tests\Feature\Admin;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Filament\Widgets\MarketplaceStats;
use App\Models\BlockedEmailDomain;
use App\Models\BlockedIp;
use App\Models\Listing;
use App\Models\Setting;
use App\Models\UnlockRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function registration(array $overrides = []): array
    {
        return [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ];
    }

    public function test_only_admins_can_open_the_admin_panel(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();

        foreach (['users', 'listings', 'reports', 'categories', 'comments', 'unlock-requests', 'blocked-ips', 'blocked-email-domains', 'giveaways'] as $resource) {
            $this->actingAs($admin)->get("/admin/{$resource}")->assertOk();
        }
    }

    public function test_moderators_get_the_review_tools_but_not_the_admin_ones(): void
    {
        $moderator = User::factory()->moderator()->create();

        foreach (['', '/listings', '/reports', '/comments', '/unlock-requests'] as $path) {
            $this->actingAs($moderator)->get("/admin{$path}")->assertOk();
        }

        foreach (['/users', '/categories', '/blocked-ips', '/blocked-email-domains', '/giveaways'] as $path) {
            $this->actingAs($moderator)->get("/admin{$path}")->assertForbidden();
        }
    }

    public function test_the_dashboard_statistics_count_auctions_revenue_and_new_members(): void
    {
        $winner = User::factory()->create();
        Listing::factory()->create();
        Listing::factory()->status(AuctionStatus::Completed)->create(['winner_id' => $winner->id, 'winning_price' => 1_500_000]);
        Listing::factory()->status(AuctionStatus::Completed)->create(['winner_id' => $winner->id, 'winning_price' => 500_000]);
        Listing::factory()->create(['auction_id' => null, 'moderation_status' => ModerationStatus::Pending]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(MarketplaceStats::class)
            ->assertSee('Phiên đấu giá')
            ->assertSee('2.000.000 đ')
            ->assertSee('1 đang diễn ra')
            ->assertSee('Tin chờ duyệt');
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();
        $user->update(['role' => 'admin']);

        $this->assertFalse($user->refresh()->isStaff());
    }

    public function test_registration_is_refused_for_a_blocked_email_domain(): void
    {
        BlockedEmailDomain::query()->create(['domain' => 'spam.test']);

        $this->post(route('register.store'), $this->registration(['email' => 'someone@SPAM.test']))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_is_refused_for_a_blocked_ip(): void
    {
        BlockedIp::query()->create(['address' => '203.0.113.9']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->post(route('register.store'), $this->registration())
            ->assertSessionHasErrors('email');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post(route('register.store'), $this->registration())
            ->assertSessionHasNoErrors();
    }

    public function test_the_password_policy_set_by_an_admin_applies_to_registration(): void
    {
        Setting::write(Setting::PASSWORD_POLICY, ['min_length' => 10, 'mixed_case' => true, 'numbers' => true, 'symbols' => false]);

        $this->post(route('register.store'), $this->registration())->assertSessionHasErrors('password');

        $this->post(route('register.store'), $this->registration([
            'password' => 'Abcdefgh12',
            'password_confirmation' => 'Abcdefgh12',
        ]))->assertSessionHasNoErrors();
    }

    public function test_a_blocked_user_is_signed_out_and_can_ask_to_be_unlocked(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['blocked_at' => now(), 'block_reason' => 'Spam'])->save();

        $this->actingAs($user)->get(route('home'))->assertRedirect(route('blocked'));
        $this->assertGuest();

        $this->post(route('unlock-requests.store'), ['email' => $user->email, 'description' => 'Nhầm lẫn'])
            ->assertRedirect(route('blocked'));

        $this->assertSame($user->email, UnlockRequest::query()->sole()->email);
    }
}

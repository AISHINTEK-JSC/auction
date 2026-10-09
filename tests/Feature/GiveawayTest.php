<?php

namespace Tests\Feature;

use App\Filament\Resources\Giveaways\Pages\ManageGiveaways;
use App\Models\Giveaway;
use App\Models\GiveawayEntry;
use App\Models\User;
use App\Services\GiveawayService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class GiveawayTest extends TestCase
{
    use RefreshDatabase;

    private function giveaway(array $attributes = []): Giveaway
    {
        return Giveaway::query()->create([
            'title' => 'Tặng áo',
            'prize' => 'Áo thun',
            'winner_count' => 1,
            'ends_at' => now()->addDay(),
            ...$attributes,
        ]);
    }

    public function test_every_member_gets_a_unique_number_that_never_changes(): void
    {
        $users = User::factory()->count(30)->create();

        $codes = $users->pluck('member_code');
        $this->assertCount(30, $codes->unique());
        $codes->each(fn (string $code) => $this->assertMatchesRegularExpression('/^\d{6}$/', $code));

        $user = $users->first();
        $code = $user->member_code;

        $user->update(['name' => 'Tên mới', 'member_code' => '000000']);
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Khác', 'email' => $user->email, 'member_code' => '111111']);

        $this->assertSame($code, $user->refresh()->member_code);
    }

    public function test_a_member_joins_a_giveaway_once(): void
    {
        $giveaway = $this->giveaway();
        $user = User::factory()->create();

        $this->get(route('giveaways.index'))->assertInertia(fn (Assert $page) => $page
            ->component('giveaways/Index')
            ->where('giveaways.data.0.joined', false)
            ->where('giveaways.data.0.open', true));

        $this->actingAs($user)->post(route('giveaways.join', $giveaway))->assertRedirect();
        $this->actingAs($user)->post(route('giveaways.join', $giveaway))->assertRedirect();

        $this->assertSame(1, $giveaway->entries()->count());

        $this->actingAs($user)->get(route('giveaways.show', $giveaway))->assertInertia(fn (Assert $page) => $page
            ->component('giveaways/Show')
            ->where('giveaway.joined', true)
            ->where('giveaway.entry_count', 1)
            ->where('auth.user.member_code', $user->member_code));

        $this->actingAs($user)->get(route('giveaways.index'))->assertInertia(fn (Assert $page) => $page
            ->where('giveaways.data.0.joined', true));
    }

    public function test_guests_unverified_members_and_closed_giveaways_cannot_be_joined(): void
    {
        $giveaway = $this->giveaway();

        $this->post(route('giveaways.join', $giveaway))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->post(route('giveaways.join', $giveaway))
            ->assertRedirect(route('verification.notice'));

        $closed = $this->giveaway(['ends_at' => now()->subMinute()]);
        $this->actingAs(User::factory()->create())->post(route('giveaways.join', $closed));

        $this->assertDatabaseCount('giveaway_entries', 0);
    }

    public function test_the_draw_picks_the_requested_number_of_winners_and_notifies_them(): void
    {
        $giveaway = $this->giveaway(['winner_count' => 2]);
        $users = User::factory()->count(5)->create();
        $blocked = User::factory()->create();
        $blocked->forceFill(['blocked_at' => now()])->save();

        foreach ([...$users, $blocked] as $user) {
            GiveawayEntry::query()->create(['giveaway_id' => $giveaway->id, 'user_id' => $user->id]);
        }

        $winners = app(GiveawayService::class)->draw($giveaway);

        $this->assertCount(2, $winners);
        $this->assertSame(2, $giveaway->winners()->count());
        $this->assertNotNull($giveaway->refresh()->drawn_at);
        $this->assertFalse($giveaway->isOpen());
        $this->assertNotContains($blocked->id, $winners->pluck('user_id'));

        foreach ($winners as $entry) {
            $this->assertSame('giveaway', $entry->user->notifications()->sole()->data['kind']);
        }

        // Winners are public by name and member number.
        $this->get(route('giveaways.show', $giveaway))->assertInertia(fn (Assert $page) => $page
            ->has('giveaway.winners', 2)
            ->where('giveaway.winners.0.member_code', $winners->sortBy('id')->first()->user->member_code));

        // A giveaway is drawn once, and nobody can join afterwards.
        $this->expectException(DomainException::class);
        app(GiveawayService::class)->draw($giveaway);
    }

    public function test_a_new_giveaway_is_announced_to_every_member_who_can_join(): void
    {
        $admin = User::factory()->admin()->create();
        $members = User::factory()->count(3)->create();
        $unverified = User::factory()->unverified()->create();
        $blocked = User::factory()->create();
        $blocked->forceFill(['blocked_at' => now()])->save();

        $giveaway = $this->giveaway(['title' => 'Tặng mũ', 'prize' => 'Mũ lưỡi trai']);

        $this->assertSame(3, app(GiveawayService::class)->announce($giveaway, $admin));

        foreach ($members as $member) {
            $notification = $member->notifications()->sole();
            $this->assertSame('giveaway_new', $notification->data['kind']);
            $this->assertSame('Quà tặng mới: Tặng mũ', $notification->data['title']);
            $this->assertStringContainsString('Mũ lưỡi trai', $notification->data['body']);
            $this->assertSame(route('giveaways.show', $giveaway, absolute: false), $notification->data['url']);
        }

        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame(0, $unverified->notifications()->count());
        $this->assertSame(0, $blocked->notifications()->count());

        // The bell shows it and following it leads to the giveaway.
        $member = $members->first();
        $this->actingAs($member)->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 1));
        $this->actingAs($member)->get(route('notifications.show', $member->notifications()->sole()->id))
            ->assertRedirect(route('giveaways.show', $giveaway, absolute: false));
    }

    public function test_the_announcement_is_emailed_through_the_queue_but_shown_in_the_app_at_once(): void
    {
        config(['queue.default' => 'database']);

        $member = User::factory()->create();
        $giveaway = $this->giveaway();

        app(GiveawayService::class)->announce($giveaway);

        // In the app immediately; the email waits for the queue worker.
        $this->assertSame(1, $member->notifications()->count());
        $this->assertDatabaseCount('jobs', 1);
        Mail::fake();

        $this->artisan('queue:work', ['--once' => true])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(1, $member->notifications()->count());
    }

    public function test_a_giveaway_that_is_already_closed_is_not_announced(): void
    {
        User::factory()->create();

        $this->assertSame(0, app(GiveawayService::class)->announce($this->giveaway(['ends_at' => now()->subMinute()])));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_creating_a_giveaway_in_the_admin_panel_announces_it(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ManageGiveaways::class)
            ->callAction('create', [
                'title' => 'Tặng sổ tay',
                'prize' => 'Sổ tay',
                'winner_count' => 1,
                'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ])
            ->assertHasNoActionErrors();

        $giveaway = Giveaway::query()->sole();
        $this->assertSame('Tặng sổ tay', $giveaway->title);
        $this->assertSame('giveaway_new', $member->notifications()->sole()->data['kind']);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_a_draw_with_fewer_entries_than_prizes_makes_everyone_a_winner(): void
    {
        $giveaway = $this->giveaway(['winner_count' => 5]);
        GiveawayEntry::query()->create(['giveaway_id' => $giveaway->id, 'user_id' => User::factory()->create()->id]);

        $this->assertCount(1, app(GiveawayService::class)->draw($giveaway));
    }

    public function test_a_giveaway_without_entries_cannot_be_drawn(): void
    {
        $giveaway = $this->giveaway();

        try {
            app(GiveawayService::class)->draw($giveaway);
            $this->fail('Expected a DomainException.');
        } catch (DomainException) {
            $this->assertNull($giveaway->refresh()->drawn_at);
        }
    }
}

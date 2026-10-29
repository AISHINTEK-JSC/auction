<?php

namespace Tests\Feature\Auth;

use App\Models\BlockedEmailDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

/**
 * "Tiếp tục với Google" on the sign-in and sign-up pages.
 */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']);
    }

    /**
     * Google answers the callback with this account.
     */
    private function googleReturns(string $id = 'google-1', string $email = 'an@gmail.com', bool $verified = true): void
    {
        $user = (new GoogleUser)
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified])
            ->map(['id' => $id, 'name' => 'Nguyễn Văn An', 'email' => $email]);

        Socialite::shouldReceive('driver->user')->andReturn($user);
    }

    public function test_the_button_shows_only_when_a_google_client_is_configured(): void
    {
        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('canSignInWithGoogle', true));
        $this->get(route('register'))
            ->assertInertia(fn (Assert $page) => $page->where('canSignInWithGoogle', true));

        config(['services.google.client_id' => null]);

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('canSignInWithGoogle', false));
        $this->get(route('google.redirect'))->assertNotFound();
        $this->get(route('google.callback'))->assertNotFound();
    }

    public function test_the_visitor_is_sent_to_google(): void
    {
        $this->get(route('google.redirect'))
            ->assertRedirectContains('accounts.google.com');
    }

    public function test_a_new_google_account_becomes_a_verified_member(): void
    {
        $this->googleReturns();

        $this->get(route('google.callback'))->assertRedirect(config('fortify.home'));

        $user = User::query()->where('email', 'an@gmail.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-1', $user->google_id);
        $this->assertSame('Nguyễn Văn An', $user->name);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotNull($user->member_code);
    }

    public function test_a_member_with_the_same_verified_email_is_linked_and_keeps_their_password(): void
    {
        $user = User::factory()->create(['email' => 'an@gmail.com']);
        $this->googleReturns();

        $this->get(route('google.callback'))->assertRedirect(config('fortify.home'));

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('google-1', $user->google_id);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertSame(1, User::query()->count());
    }

    public function test_an_account_registered_with_an_unconfirmed_email_is_taken_over_by_the_owner_of_the_address(): void
    {
        // Someone else may have registered this address and set its password.
        $user = User::factory()->unverified()->create(['email' => 'an@gmail.com']);
        $this->googleReturns();

        $this->get(route('google.callback'))->assertRedirect(config('fortify.home'));

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_a_linked_member_is_found_by_google_id_after_changing_their_email(): void
    {
        $user = User::factory()->create(['email' => 'new@example.com']);
        $user->forceFill(['google_id' => 'google-1'])->save();
        $this->googleReturns(email: 'an@gmail.com');

        $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->count());
    }

    public function test_an_email_linked_to_another_google_account_is_refused(): void
    {
        $user = User::factory()->create(['email' => 'an@gmail.com']);
        $user->forceFill(['google_id' => 'google-other'])->save();
        $this->googleReturns();

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_an_unverified_google_email_is_refused(): void
    {
        $this->googleReturns(verified: false);

        $this->get(route('google.callback'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_blocked_email_domain_cannot_sign_up_with_google(): void
    {
        BlockedEmailDomain::query()->create(['domain' => 'gmail.com']);
        $this->googleReturns();

        $this->get(route('google.callback'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_failed_round_trip_sends_the_visitor_back_to_sign_in(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_a_member_with_two_factor_still_has_to_give_the_code(): void
    {
        $user = User::factory()->withTwoFactor()->create(['email' => 'an@gmail.com']);
        $user->forceFill(['google_id' => 'google-1'])->save();
        $this->googleReturns();

        $this->get(route('google.callback'))
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $user->id);

        $this->assertGuest();
    }
}

<?php

namespace Tests\Feature\Settings;

use App\Enums\Gender;
use App\Models\City;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_gender_birthday_and_shipping_address_can_be_saved(): void
    {
        $user = User::factory()->create();
        $city = City::query()->create(['name' => 'Hà Nội']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'gender' => 'female',
            'birthday' => '1995-08-20',
            'shipping_name' => 'Nguyễn Thị Lan',
            'shipping_phone' => '0912345678',
            'shipping_city_id' => $city->id,
            'shipping_address' => '12 Lê Lợi, phường Bến Nghé',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(Gender::Female, $user->gender);
        $this->assertSame('1995-08-20', $user->birthday?->format('Y-m-d'));
        // Unchanged email: still verified.
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('12 Lê Lợi, phường Bến Nghé, Hà Nội', $user->shippingAddress?->oneLine());
        $this->assertSame('Nguyễn Thị Lan', $user->shippingAddress->recipient_name);

        // The form gets them back in the shape of its fields.
        $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.gender', 'female')
            ->where('auth.user.birthday', '1995-08-20')
            ->where('shippingAddress', ['name' => 'Nguyễn Thị Lan', 'phone' => '0912345678', 'city_id' => $city->id, 'address' => '12 Lê Lợi, phường Bến Nghé'])
            ->has('genders', 3));

        // Saving again replaces the address instead of adding a second one.
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            // "Không muốn cho biết": the form sends the field empty.
            'gender' => '',
            'shipping_name' => 'Lan',
            'shipping_phone' => '0912345678',
            'shipping_city_id' => $city->id,
            'shipping_address' => '99 Hai Bà Trưng',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ShippingAddress::query()->count());
        $this->assertSame('99 Hai Bà Trưng', $user->fresh()->shippingAddress->address);
        $this->assertNull($user->fresh()->gender);
        // Not sent at all, so untouched.
        $this->assertSame('1995-08-20', $user->fresh()->birthday?->format('Y-m-d'));
    }

    public function test_the_shipping_address_is_all_or_nothing(): void
    {
        $user = User::factory()->create();
        $city = City::query()->create(['name' => 'Hà Nội']);
        $profile = ['name' => $user->name, 'email' => $user->email];

        $this->actingAs($user)->patch(route('profile.update'), [...$profile, 'shipping_name' => 'Lan'])
            ->assertSessionHasErrors(['shipping_phone', 'shipping_city_id', 'shipping_address']);
        $this->actingAs($user)->patch(route('profile.update'), [...$profile, 'shipping_name' => 'Lan', 'shipping_phone' => '12345', 'shipping_city_id' => 999, 'shipping_address' => 'x'])
            ->assertSessionHasErrors(['shipping_phone', 'shipping_city_id']);
        $this->assertSame(0, ShippingAddress::query()->count());

        $user->shippingAddress()->create(['recipient_name' => 'Lan', 'phone' => '0912345678', 'city_id' => $city->id, 'address' => '12 Lê Lợi']);

        // A request that does not carry the address at all leaves it alone…
        $this->actingAs($user)->patch(route('profile.update'), $profile)->assertSessionHasNoErrors();
        $this->assertSame(1, ShippingAddress::query()->count());

        // …while the form sent with its four fields emptied removes it.
        $this->actingAs($user)->patch(route('profile.update'), [...$profile, 'shipping_name' => '', 'shipping_phone' => '', 'shipping_city_id' => '', 'shipping_address' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, ShippingAddress::query()->count());
    }

    public function test_gender_and_birthday_must_make_sense(): void
    {
        $user = User::factory()->create();
        $profile = ['name' => $user->name, 'email' => $user->email];

        $this->actingAs($user)->patch(route('profile.update'), [...$profile, 'gender' => 'robot'])->assertSessionHasErrors('gender');
        $this->actingAs($user)->patch(route('profile.update'), [...$profile, 'birthday' => now()->addDay()->format('Y-m-d')])->assertSessionHasErrors('birthday');
        $this->actingAs($user)->patch(route('profile.update'), [...$profile, 'birthday' => '1850-01-01'])->assertSessionHasErrors('birthday');
        $this->actingAs($user)->patch(route('profile.update'), [...$profile, 'birthday' => '20/08/1995'])->assertSessionHasErrors('birthday');

        $this->assertNull($user->fresh()->birthday);
    }

    public function test_private_details_stay_off_the_public_profile(): void
    {
        $user = User::factory()->create(['gender' => 'male', 'birthday' => '1990-01-01']);
        $user->shippingAddress()->create(['recipient_name' => 'Người nhận kín', 'phone' => '0912345678', 'address' => 'Ngõ bí mật 7']);

        $response = $this->get(route('users.show', $user))->assertOk();

        $response->assertDontSee('1990-01-01', false)->assertDontSee('Ngõ bí mật', false)->assertDontSee('Người nhận kín', false);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}

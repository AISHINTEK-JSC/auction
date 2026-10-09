<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The captcha (Cloudflare Turnstile) on registration and on posting a listing.
 * It is off unless both keys are configured; the other tests run with it off.
 */
class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    private const FIELD = 'cf-turnstile-response';

    private const VERIFY = 'challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function turnOn(): void
    {
        config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret_key' => 'secret-key']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function listing(array $overrides = []): array
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

    public function test_the_pages_get_the_site_key_only_while_the_captcha_is_on(): void
    {
        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('captchaSiteKey', null));

        // One key alone does not turn it on.
        config(['services.turnstile.site_key' => 'site-key']);
        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('captchaSiteKey', null));

        $this->turnOn();
        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('captchaSiteKey', 'site-key'));
    }

    public function test_registration_is_refused_without_a_token(): void
    {
        $this->turnOn();
        Http::fake();

        $this->post(route('register.store'), $this->registration())->assertSessionHasErrors(self::FIELD);

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
        Http::assertNothingSent();
    }

    public function test_registration_is_refused_when_cloudflare_rejects_the_token(): void
    {
        $this->turnOn();
        Http::fake([self::VERIFY => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        $this->post(route('register.store'), $this->registration([self::FIELD => 'forged']))
            ->assertSessionHasErrors(self::FIELD);

        $this->assertSame(0, User::query()->count());
    }

    public function test_registration_goes_through_with_a_genuine_token(): void
    {
        $this->turnOn();
        Http::fake([self::VERIFY => Http::response(['success' => true])]);

        $this->post(route('register.store'), $this->registration([self::FIELD => 'genuine']))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
        Http::assertSent(fn (Request $request) => $request['secret'] === 'secret-key' && $request['response'] === 'genuine');
    }

    public function test_nothing_gets_through_while_cloudflare_cannot_be_reached(): void
    {
        $this->turnOn();
        Http::fake([self::VERIFY => fn () => throw new ConnectionException('timeout')]);

        $this->post(route('register.store'), $this->registration([self::FIELD => 'genuine']))
            ->assertSessionHasErrors(self::FIELD);

        $this->assertSame(0, User::query()->count());
    }

    public function test_posting_a_listing_asks_for_the_captcha_but_saving_a_draft_does_not(): void
    {
        Storage::fake('public');
        $this->turnOn();
        Http::fake([self::VERIFY => Http::response(['success' => true])]);
        $seller = User::factory()->create();

        $this->actingAs($seller)->post(route('listings.store'), $this->listing())
            ->assertSessionHasErrors(self::FIELD);
        $this->assertSame(0, Listing::query()->count());

        $this->actingAs($seller)->post(route('listings.drafts.store'), ['title' => 'Bàn làm việc'])
            ->assertSessionHasNoErrors();
        $draft = Listing::query()->sole();
        Http::assertNothingSent();

        // Publishing the draft is posting: the captcha is back.
        $this->actingAs($seller)->post(route('listings.drafts.publish', $draft), $this->listing())
            ->assertSessionHasErrors(self::FIELD);
        $this->assertTrue($draft->fresh()->isDraft());

        $this->actingAs($seller)->post(route('listings.drafts.publish', $draft), $this->listing([self::FIELD => 'genuine']))
            ->assertSessionHasNoErrors();
        $this->assertTrue($draft->fresh()->isPublished());
    }
}

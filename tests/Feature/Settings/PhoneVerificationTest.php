<?php

namespace Tests\Feature\Settings;

use App\Models\Listing;
use App\Models\User;
use App\Services\Sms\SmsSender;
use App\Services\Sms\SnsSmsSender;
use Aws\Sns\SnsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * A member confirms their phone number by typing back a code texted to it;
 * bidding and posting listings wait until they have.
 */
class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{phone: string, message: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SmsSender::class, new class($this->sent) implements SmsSender
        {
            /** @param  list<array{phone: string, message: string}>  $sent */
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message): void
            {
                $this->sent[] = ['phone' => $phone, 'message' => $message];
            }
        });
    }

    private function sentCode(): string
    {
        $this->assertNotEmpty($this->sent);
        preg_match('/\b(\d{6})\b/', end($this->sent)['message'], $match);

        return $match[1];
    }

    public function test_a_member_confirms_their_number_with_the_texted_code(): void
    {
        $user = User::factory()->unverifiedPhone()->create(['phone' => '0912345678']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('phoneCodePending', false));

        $this->actingAs($user)->post(route('phone.verification.send'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertCount(1, $this->sent);
        $this->assertSame('+84912345678', $this->sent[0]['phone']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('phoneCodePending', true));

        $this->actingAs($user)->put(route('phone.verification.verify'), ['code' => $this->sentCode()])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue($user->fresh()->hasVerifiedPhone());
    }

    public function test_a_wrong_code_is_refused_and_too_many_wrong_codes_void_it(): void
    {
        $user = User::factory()->unverifiedPhone()->create(['phone' => '0912345678']);

        $this->actingAs($user)->post(route('phone.verification.send'));
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->actingAs($user)->put(route('phone.verification.verify'), ['code' => $wrong])
            ->assertSessionHasErrors(['code' => 'Mã xác thực không đúng.']);

        foreach (range(2, 5) as $ignored) {
            $this->actingAs($user)->put(route('phone.verification.verify'), ['code' => $wrong]);
        }

        // The right code no longer works once the attempts are used up.
        $this->actingAs($user)->put(route('phone.verification.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedPhone());
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = User::factory()->unverifiedPhone()->create(['phone' => '0912345678']);

        $this->actingAs($user)->post(route('phone.verification.send'));

        $this->travel(6)->minutes();

        $this->actingAs($user)->put(route('phone.verification.verify'), ['code' => $this->sentCode()])
            ->assertSessionHasErrors(['code' => 'Mã đã hết hạn. Vui lòng gửi mã mới.']);
    }

    public function test_codes_cannot_be_asked_for_too_often(): void
    {
        $user = User::factory()->unverifiedPhone()->create(['phone' => '0912345678']);

        $this->actingAs($user)->post(route('phone.verification.send'))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('phone.verification.send'))->assertSessionHasErrors('code');
        $this->assertCount(1, $this->sent);

        // A minute later another one can go out, up to the daily limit.
        foreach (range(2, 5) as $ignored) {
            $this->travel(61)->seconds();
            $this->actingAs($user)->post(route('phone.verification.send'))->assertSessionHasNoErrors();
        }

        $this->travel(61)->seconds();
        $this->actingAs($user)->post(route('phone.verification.send'))->assertSessionHasErrors('code');
        $this->assertCount(5, $this->sent);
    }

    public function test_a_number_confirmed_by_another_account_cannot_be_confirmed_again(): void
    {
        User::factory()->create(['phone' => '+84912345678']);
        $user = User::factory()->unverifiedPhone()->create(['phone' => '0912345678']);

        $this->actingAs($user)->post(route('phone.verification.send'))
            ->assertSessionHasErrors(['code' => 'Số điện thoại này đã được xác thực cho một tài khoản khác.']);

        $this->assertSame([], $this->sent);
    }

    public function test_a_gateway_failure_is_reported_to_the_member(): void
    {
        $this->app->instance(SmsSender::class, new class implements SmsSender
        {
            public function send(string $phone, string $message): void
            {
                throw new RuntimeException('SNS is down');
            }
        });

        $user = User::factory()->unverifiedPhone()->create(['phone' => '0912345678']);

        $this->actingAs($user)->post(route('phone.verification.send'))
            ->assertSessionHasErrors(['code' => 'Không gửi được tin nhắn. Vui lòng thử lại sau ít phút.']);
    }

    public function test_changing_the_number_asks_for_a_new_confirmation(): void
    {
        $user = User::factory()->create(['phone' => '0912345678']);
        $this->assertTrue($user->hasVerifiedPhone());

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '0987654321',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->hasVerifiedPhone());

        // Saving the profile with the same number keeps the confirmation.
        $other = User::factory()->create(['phone' => '0911111111']);
        $this->actingAs($other)->patch(route('profile.update'), [
            'name' => 'Tên mới',
            'email' => $other->email,
            'phone' => '0911111111',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($other->fresh()->hasVerifiedPhone());
    }

    public function test_bidding_and_posting_need_a_confirmed_number(): void
    {
        $user = User::factory()->unverifiedPhone()->create();
        $listing = Listing::factory()->create();

        $this->actingAs($user)->get(route('listings.create'))->assertRedirect(route('profile.edit'));
        $this->actingAs($user)->post(route('listings.store'))->assertRedirect(route('profile.edit'));
        $this->actingAs($user)->post(route('listings.bids.store', $listing), ['bid_type' => 1, 'bid_price' => 100_000])
            ->assertRedirect(route('profile.edit'));

        // Looking around is still allowed.
        $this->actingAs($user)->get(route('listings.show', $listing))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->actingAs(User::factory()->create())->get(route('listings.create'))->assertOk();
    }

    public function test_the_sns_sender_publishes_a_transactional_message(): void
    {
        // A mock, not a real client: building one loads the SDK's large API definition.
        $client = Mockery::mock(SnsClient::class);
        $client->shouldReceive('publish')->once()->with([
            'PhoneNumber' => '+84912345678',
            'Message' => 'Ma: 123456',
            'MessageAttributes' => [
                'AWS.SNS.SMS.SMSType' => ['DataType' => 'String', 'StringValue' => 'Transactional'],
                'AWS.SNS.SMS.SenderID' => ['DataType' => 'String', 'StringValue' => 'CHOTGIA'],
            ],
        ]);

        (new SnsSmsSender($client, 'CHOTGIA'))->send('+84912345678', 'Ma: 123456');
    }
}

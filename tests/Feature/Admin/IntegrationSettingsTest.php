<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\MarketplaceSettingsPage;
use App\Models\AdminAction;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\Sms\SmsSender;
use App\Support\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The SMTP server and the auction engine can be set in the admin panel; what
 * is saved there takes over from .env, and secrets stay secret.
 */
class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const SMTP = [
        'host' => 'smtp.chotgia.test',
        'port' => '465',
        'scheme' => 'smtps',
        'username' => 'mailer',
        'password' => 'smtp-secret-123',
        'from_address' => 'thongbao@chotgia.test',
        'from_name' => 'Chốt Giá',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_saved_smtp_settings_take_over_from_the_environment(): void
    {
        $envHost = config('mail.mailers.smtp.host');

        IntegrationSettings::save(IntegrationSettings::SMTP, self::SMTP);

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.chotgia.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('smtp-secret-123', config('mail.mailers.smtp.password'));
        $this->assertSame('thongbao@chotgia.test', config('mail.from.address'));

        // The next request starts from .env again and gets the same result.
        config(['mail.mailers.smtp.host' => $envHost, 'mail.mailers.smtp.password' => null]);
        IntegrationSettings::apply();
        $this->assertSame('smtp.chotgia.test', config('mail.mailers.smtp.host'));
        $this->assertSame('smtp-secret-123', config('mail.mailers.smtp.password'));

        // An emptied field goes back to .env; an empty password keeps the saved one.
        IntegrationSettings::save(IntegrationSettings::SMTP, [...self::SMTP, 'host' => '', 'password' => '']);
        $this->assertSame($envHost, config('mail.mailers.smtp.host'));
        $this->assertSame('smtp-secret-123', config('mail.mailers.smtp.password'));

        IntegrationSettings::reset(IntegrationSettings::SMTP);
        $this->assertNull(config('mail.mailers.smtp.password'));
        $this->assertSame([], IntegrationSettings::stored(IntegrationSettings::SMTP));
    }

    public function test_secrets_are_encrypted_in_the_database(): void
    {
        IntegrationSettings::save(IntegrationSettings::SMTP, self::SMTP);
        IntegrationSettings::save(IntegrationSettings::ENGINE, ['url' => 'https://engine.chotgia.test/api/v1', 'token' => 'engine-token-abc', 'push_key' => 'push-key-xyz']);

        $raw = DB::table('settings')->pluck('value')->implode(' ');

        $this->assertStringContainsString('smtp.chotgia.test', $raw);
        foreach (['smtp-secret-123', 'engine-token-abc', 'push-key-xyz'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
        }

        // A value encrypted with another key is treated as not set, not as an error.
        Setting::write(IntegrationSettings::SMTP, ['host' => 'smtp.chotgia.test', 'password' => 'not-a-ciphertext']);
        $this->assertSame(['host' => 'smtp.chotgia.test'], IntegrationSettings::stored(IntegrationSettings::SMTP));
    }

    public function test_the_engine_client_and_the_webhook_use_the_saved_connection(): void
    {
        IntegrationSettings::save(IntegrationSettings::ENGINE, ['url' => 'https://engine.chotgia.test/api/v1/', 'token' => 'engine-token-abc', 'push_key' => 'push-key-xyz']);

        Http::fake(['engine.chotgia.test/api/v1/auctions' => Http::response(['success' => true, 'message' => null, 'data' => []])]);

        $this->assertSame([], app(AuctionEngineClient::class)->listAuctions());
        Http::assertSent(fn (Request $request) => $request->url() === 'https://engine.chotgia.test/api/v1/auctions'
            && $request->hasHeader('Authorization', 'Bearer engine-token-abc'));

        // The engine's notices are accepted with the new push key only: with it
        // the call gets past the key check (and is told its auction is unknown).
        $this->withToken('push-key-xyz')->get(route('webhooks.auction', ['auction' => ['auction_id' => 999]]))->assertNotFound();
        $this->withToken('some-other-key')->get(route('webhooks.auction', ['auction' => ['auction_id' => 999]]))->assertUnauthorized();
    }

    public function test_an_admin_sets_the_mail_server_and_never_sees_the_password_again(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('smtp', [...self::SMTP, 'reset' => false])
            ->assertHasNoActionErrors();

        $this->assertSame('smtp.chotgia.test', config('mail.mailers.smtp.host'));

        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertSee('smtp.chotgia.test:465')
            ->assertSee('Đã đặt (trang quản trị)')
            ->assertDontSee('smtp-secret-123');

        // The form comes back without the password, and saving it empty keeps the password.
        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->mountAction('smtp')
            ->assertActionDataSet(['host' => 'smtp.chotgia.test', 'password' => null])
            ->setActionData(['from_name' => 'Chốt Giá VN'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('smtp-secret-123', config('mail.mailers.smtp.password'));
        $this->assertSame('Chốt Giá VN', config('mail.from.name'));

        $summaries = AdminAction::query()->where('action', 'settings.updated')->pluck('summary')->implode(' | ');
        $this->assertStringContainsString('smtp.chotgia.test:465', $summaries);
        $this->assertStringContainsString('Mật khẩu SMTP', $summaries);
        $this->assertStringNotContainsString('smtp-secret-123', $summaries);

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('smtp', ['port' => '99999', 'from_address' => 'không phải email', 'reset' => false])
            ->assertHasActionErrors(['port', 'from_address']);

        // "Dùng lại .env" drops everything, the password included.
        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('smtp', ['reset' => true])
            ->assertHasNoActionErrors();
        $this->assertSame([], IntegrationSettings::stored(IntegrationSettings::SMTP));
    }

    public function test_an_admin_sets_the_engine_connection_and_can_test_it(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('engine', ['url' => 'không phải địa chỉ', 'reset' => false])
            ->assertHasActionErrors(['url']);

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('engine', ['url' => 'https://engine.chotgia.test/api/v1', 'token' => 'engine-token-abc', 'push_key' => 'push-key-xyz', 'reset' => false])
            ->assertHasNoActionErrors();

        $this->actingAs($admin)->get('/admin/settings')
            ->assertSee('https://engine.chotgia.test/api/v1')
            ->assertDontSee('engine-token-abc')
            ->assertDontSee('push-key-xyz');

        $log = AdminAction::query()->where('action', 'settings.updated')->sole()->summary;
        $this->assertStringContainsString('API token của engine: đã đổi', $log);
        $this->assertStringNotContainsString('engine-token-abc', $log);

        Http::fake(['engine.chotgia.test/api/v1/auctions' => Http::sequence()
            ->push(['success' => true, 'message' => null, 'data' => [['auction_id' => 1], ['auction_id' => 2]]])
            ->push(['success' => false, 'message' => 'Unauthenticated', 'error_code' => 'E_99'])]);

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('testEngine')
            ->assertNotified('Kết nối tới engine hoạt động');

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('testEngine')
            ->assertNotified('Không kết nối được tới engine');
    }

    public function test_the_test_email_goes_out_or_reports_why_it_could_not(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@chotgia.test']);

        // Tests run on the in-memory mailer: the message can be inspected.
        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('testMail', ['to' => 'admin@chotgia.test'])
            ->assertHasNoActionErrors()
            ->assertNotified('Đã gửi email thử tới admin@chotgia.test');

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame('admin@chotgia.test', $sent->first()->getEnvelope()->getRecipients()[0]->getAddress());

        // A server nobody listens on: the failure is shown, not swallowed.
        IntegrationSettings::save(IntegrationSettings::SMTP, ['host' => '127.0.0.1', 'port' => '1']);

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('testMail', ['to' => 'admin@chotgia.test'])
            ->assertNotified('Không gửi được email');
    }

    public function test_an_admin_switches_email_to_amazon_ses(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('smtp', ['mailer' => 'ses', 'ses_key' => 'AKIAEXAMPLE12345678', 'ses_secret' => 'ses-secret-abc', 'ses_region' => 'ap-southeast-1', 'from_address' => 'no-reply@chotgia.test', 'reset' => false])
            ->assertHasNoActionErrors();

        $this->assertSame('ses', config('mail.default'));
        $this->assertSame('AKIAEXAMPLE12345678', config('services.ses.key'));
        $this->assertSame('ses-secret-abc', config('services.ses.secret'));
        $this->assertSame('ap-southeast-1', config('services.ses.region'));
        $this->assertStringNotContainsString('ses-secret-abc', DB::table('settings')->pluck('value')->implode(' '));

        $this->actingAs($admin)->get('/admin/settings')
            ->assertSee('Amazon SES (ap-southeast-1)')
            ->assertSee('AKIA…5678')
            ->assertDontSee('ses-secret-abc');

        $log = AdminAction::query()->where('action', 'settings.updated')->sole()->summary;
        $this->assertStringContainsString('Secret key SES: đã đổi', $log);
        $this->assertStringNotContainsString('ses-secret-abc', $log);

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('smtp', ['ses_region' => 'không phải vùng', 'reset' => false])
            ->assertHasActionErrors(['ses_region']);

        // "Dùng lại .env" drops the SES keys too.
        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('smtp', ['reset' => true])
            ->assertHasNoActionErrors();
        $this->assertSame([], IntegrationSettings::stored(IntegrationSettings::SES));
        $this->assertSame('array', config('mail.default'));
    }

    public function test_an_admin_sets_up_amazon_sns_and_sends_a_test_text(): void
    {
        $admin = User::factory()->admin()->create(['phone' => '0912345678']);

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('sms', ['driver' => 'sns', 'key' => 'AKIASNSEXAMPLE1234', 'secret' => 'sns-secret-xyz', 'region' => 'ap-southeast-1', 'sender_id' => 'CHOTGIA', 'reset' => false])
            ->assertHasNoActionErrors();

        $this->assertSame('sns', config('services.sms.driver'));
        $this->assertSame('sns-secret-xyz', config('services.sns.secret'));

        $this->actingAs($admin)->get('/admin/settings')
            ->assertSee('Amazon SNS (ap-southeast-1)')
            ->assertSee('CHOTGIA')
            ->assertDontSee('sns-secret-xyz');

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('sms', ['sender_id' => 'quá dài và có dấu', 'reset' => false])
            ->assertHasActionErrors(['sender_id']);

        $sent = [];
        $this->app->instance(SmsSender::class, new class($sent) implements SmsSender
        {
            /** @param  list<string>  $sent */
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message): void
            {
                $this->sent[] = $phone;
            }
        });

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('testSms', ['to' => '0912345678'])
            ->assertHasNoActionErrors()
            ->assertNotified('Đã gửi SMS thử tới +84912345678');
        $this->assertSame(['+84912345678'], $sent);

        Livewire::actingAs($admin)->test(MarketplaceSettingsPage::class)
            ->callAction('sms', ['reset' => true])
            ->assertHasNoActionErrors();
        $this->assertSame('log', config('services.sms.driver'));
    }

    public function test_moderators_cannot_reach_the_connection_settings(): void
    {
        $this->actingAs(User::factory()->moderator()->create())->get('/admin/settings')->assertForbidden();
    }
}

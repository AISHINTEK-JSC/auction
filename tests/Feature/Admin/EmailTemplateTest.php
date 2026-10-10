<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\EmailTemplatesPage;
use App\Models\AdminAction;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Support\EmailTemplate;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The site's emails share one layout and take their wording from templates
 * an admin can edit.
 */
class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function html(MarketplaceNotification $notification, User $user): string
    {
        return (string) $notification->toMail($user)->render();
    }

    public function test_an_email_comes_in_the_sites_layout_and_in_vietnamese(): void
    {
        $user = User::factory()->create(['name' => 'Trần Thị Mai']);
        $listing = Listing::factory()->create(['title' => 'Đồng hồ Seiko', 'winning_price' => 1500000]);

        $notification = MarketplaceNotification::won($listing);
        $message = $notification->toMail($user);
        $html = (string) $message->render();

        $this->assertSame('Bạn đã thắng phiên đấu giá', $message->subject);
        $this->assertStringContainsString('Xin chào Trần Thị Mai,', $html);
        $this->assertStringContainsString('Bạn thắng "Đồng hồ Seiko" với giá 1.500.000 đ.', html_entity_decode($html));
        $this->assertStringContainsString('Xem chi tiết', $html);
        $this->assertStringContainsString(route('listings.show', $listing), $html);
        $this->assertStringContainsString('#059669', $html);
        $this->assertStringContainsString('apple-touch-icon.png', $html);
        $this->assertStringContainsString('Trân trọng,', $html);
        $this->assertStringContainsString('© '.date('Y').' '.config('app.name'), $html);
        // Nothing left of the framework's English boilerplate.
        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringNotContainsString('trouble clicking', $html);
    }

    public function test_the_layout_follows_the_admin_settings(): void
    {
        config(['marketplace.contact_email' => 'hotro@chotgia.test']);
        $user = User::factory()->create(['name' => 'Mai']);
        $notification = MarketplaceNotification::lost(Listing::factory()->create());

        EmailTemplate::saveLayout([
            'show_logo' => false,
            'brand_color' => '#1d4ed8',
            'greeting' => 'Chào {ten} thân mến,',
            'signature' => "Thân ái,\nĐội ngũ {site}",
            'footer' => 'Cần hỗ trợ? Viết cho {email_lien_he}',
        ]);

        $html = $this->html($notification, $user);

        $this->assertStringContainsString('Chào Mai thân mến,', $html);
        $this->assertStringContainsString('#1d4ed8', $html);
        $this->assertStringNotContainsString('#059669', $html);
        $this->assertStringNotContainsString('apple-touch-icon.png', $html);
        $this->assertStringContainsString('Đội ngũ '.config('app.name'), $html);
        $this->assertStringContainsString('Cần hỗ trợ? Viết cho hotro@chotgia.test', $html);

        // A colour that is not one falls back instead of breaking the email.
        EmailTemplate::saveLayout(['brand_color' => 'red; background: url(x)', 'greeting' => '', 'signature' => '', 'footer' => '']);
        $html = $this->html($notification, $user);
        $this->assertStringContainsString('#059669', $html);
        $this->assertStringNotContainsString('url(x)', $html);
        $this->assertStringContainsString('Xin chào Mai,', $html);

        EmailTemplate::resetLayout();
        $this->assertStringContainsString('apple-touch-icon.png', $this->html($notification, $user));
    }

    public function test_the_wording_of_a_message_can_be_changed_for_email_and_bell_alike(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create(['title' => 'Đồng hồ Seiko', 'winning_price' => 1500000]);

        EmailTemplate::saveContent('won', 'Chúc mừng, {tin} là của bạn!', "Giá chốt: {gia}.\nHãy liên hệ người bán trong hôm nay. {khong_co}", 'Xem phiên');

        $notification = MarketplaceNotification::won($listing);

        // Unknown placeholders stay as typed, so a typo is visible in the preview.
        $this->assertSame('Chúc mừng, Đồng hồ Seiko là của bạn!', $notification->title);
        $this->assertSame("Giá chốt: 1.500.000 đ.\nHãy liên hệ người bán trong hôm nay. {khong_co}", $notification->body);
        $this->assertSame($notification->title, $notification->toArray($user)['title']);

        $message = $notification->toMail($user);
        $this->assertSame('Chúc mừng, Đồng hồ Seiko là của bạn!', $message->subject);
        $this->assertSame(['Giá chốt: 1.500.000 đ.', 'Hãy liên hệ người bán trong hôm nay. {khong_co}'], $message->introLines);
        $this->assertSame('Xem phiên', $message->actionText);

        // Other kinds are untouched, and the default comes back on reset.
        $this->assertSame('Phiên đấu giá đã kết thúc', MarketplaceNotification::lost($listing)->title);
        EmailTemplate::resetContent('won');
        $this->assertSame('Bạn đã thắng phiên đấu giá', MarketplaceNotification::won($listing)->title);
    }

    public function test_an_empty_placeholder_leaves_no_gap(): void
    {
        $listing = Listing::factory()->create(['title' => 'Xe đạp']);

        $this->assertSame(
            'Kết quả phiên "Xe đạp" đã được chuyển cho người khác.',
            MarketplaceNotification::forfeitWarning($listing, null)->body,
        );
        $this->assertStringEndsWith('tạm khoá đặt giá 7 ngày.', MarketplaceNotification::forfeitWarning($listing, 7)->body);
    }

    public function test_what_members_typed_is_shown_as_text_never_run_as_markup(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create(['title' => '<b>Rẻ</b> [bấm vào đây](http://evil.test) <script>alert(1)</script>']);

        $html = $this->html(MarketplaceNotification::lost($listing), $user);

        $this->assertStringContainsString('&lt;b&gt;Rẻ&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        // The Markdown link stays the text it was typed as.
        $this->assertStringContainsString('[bấm vào đây](http://evil.test)', $html);
        $this->assertStringNotContainsString('href="http://evil.test"', $html);
    }

    public function test_the_account_emails_use_the_same_layout_and_templates(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Mai']);

        $verify = (new VerifyEmail)->toMail($user);
        $this->assertSame('Xác thực địa chỉ email', $verify->subject);
        $this->assertSame('Xác thực email', $verify->actionText);
        $this->assertStringContainsString('/email/verify/'.$user->id, $verify->actionUrl);
        $this->assertStringContainsString('Xin chào Mai,', (string) $verify->render());

        $reset = (new ResetPassword('reset-token-123'))->toMail($user);
        $this->assertSame('Đặt lại mật khẩu', $reset->subject);
        $this->assertStringContainsString('reset-token-123', $reset->actionUrl);
        $this->assertStringContainsString(urlencode($user->email), $reset->actionUrl);
        $this->assertStringContainsString('60 phút', implode(' ', $reset->introLines));

        EmailTemplate::saveContent('verify_email', 'Kích hoạt tài khoản {site}', 'Bấm nút dưới đây để kích hoạt.', 'Kích hoạt');
        $verify = (new VerifyEmail)->toMail($user);
        $this->assertSame('Kích hoạt tài khoản '.config('app.name'), $verify->subject);
        $this->assertSame('Kích hoạt', $verify->actionText);
    }

    public function test_every_template_can_be_previewed(): void
    {
        foreach (array_keys(EmailTemplate::TYPES) as $type) {
            $message = EmailTemplate::sample($type);
            $html = (string) $message->render();

            $this->assertNotSame('', (string) $message->subject, $type);
            $this->assertStringContainsString('Xin chào Nguyễn Văn An,', $html, $type);
            // Every default placeholder has a sample value.
            $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $message->subject.' '.implode(' ', $message->introLines), $type);
        }
    }

    public function test_an_admin_edits_the_layout_and_a_template_on_the_page(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@chotgia.test']);

        $this->actingAs($admin)->get('/admin/email-templates')
            ->assertOk()
            ->assertSee('Bị vượt giá')
            ->assertSee('Xác thực email khi đăng ký')
            ->assertDontSee('Đã sửa');
        $this->actingAs(User::factory()->moderator()->create())->get('/admin/email-templates')->assertForbidden();

        $layout = [...EmailTemplate::layout(), 'reset' => false];

        Livewire::actingAs($admin)->test(EmailTemplatesPage::class)
            ->callAction('editLayout', [...$layout, 'brand_color' => 'đỏ'])
            ->assertHasActionErrors(['brand_color']);

        Livewire::actingAs($admin)->test(EmailTemplatesPage::class)
            ->callAction('editLayout', [...$layout, 'brand_color' => '#1d4ed8', 'greeting' => 'Chào {ten},'])
            ->assertHasNoActionErrors();
        $this->assertSame('#1d4ed8', EmailTemplate::layout()['brand_color']);

        Livewire::actingAs($admin)->test(EmailTemplatesPage::class)
            ->callAction('editContent', ['subject' => '', 'body' => 'x', 'button' => 'x', 'reset' => false], ['type' => 'outbid'])
            ->assertHasActionErrors(['subject']);

        Livewire::actingAs($admin)->test(EmailTemplatesPage::class)
            ->callAction('editContent', ['subject' => 'Có người trả cao hơn bạn', 'body' => 'Giá mới của "{tin}" là {gia}.', 'button' => 'Đặt lại ngay', 'reset' => false], ['type' => 'outbid'])
            ->assertHasNoActionErrors();

        $this->assertSame('Có người trả cao hơn bạn', EmailTemplate::content('outbid')['subject']);
        $this->actingAs($admin)->get('/admin/email-templates')->assertSee('Có người trả cao hơn bạn')->assertSee('Đã sửa');

        // The preview shows the new wording in the new colour.
        Livewire::actingAs($admin)->test(EmailTemplatesPage::class)
            ->mountAction('preview', ['type' => 'outbid'])
            ->assertActionMounted('preview');

        // (The modal itself reaches the browser as a separate part of the response;
        // what it frames is this.)
        $preview = view('filament.email-preview', [
            'subject' => EmailTemplate::sample('outbid')->subject,
            'html' => (string) EmailTemplate::sample('outbid')->render(),
        ])->render();
        $this->assertStringContainsString('Có người trả cao hơn bạn', $preview);
        $this->assertStringContainsString('sandbox=""', $preview);
        $this->assertStringContainsString('Đặt lại ngay', $preview);
        $this->assertStringContainsString('#1d4ed8', $preview);
        // Inside the frame's attribute the email is escaped, not live markup of the admin page.
        $this->assertStringNotContainsString('<table role="presentation"', $preview);

        // A test send goes to a real mailbox (the in-memory mailer here).
        Livewire::actingAs($admin)->test(EmailTemplatesPage::class)
            ->callAction('sendTest', ['to' => 'admin@chotgia.test'], ['type' => 'outbid'])
            ->assertHasNoActionErrors()
            ->assertNotified('Đã gửi thư thử tới admin@chotgia.test');

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame('[Thử] Có người trả cao hơn bạn', $sent->first()->getOriginalMessage()->getSubject());

        Livewire::actingAs($admin)->test(EmailTemplatesPage::class)
            ->callAction('editContent', ['subject' => 'x', 'body' => 'x', 'button' => 'x', 'reset' => true], ['type' => 'outbid'])
            ->assertHasNoActionErrors();
        $this->assertFalse(EmailTemplate::content('outbid')['customized']);
        $this->assertSame('Bạn đã bị vượt giá', EmailTemplate::content('outbid')['subject']);

        $log = AdminAction::query()->where('action', 'email_template.updated')->pluck('summary')->all();
        $this->assertContains('Sửa giao diện chung của email', $log);
        $this->assertContains('Sửa mẫu email "Bị vượt giá"', $log);
        $this->assertContains('Đưa về mặc định mẫu email "Bị vượt giá"', $log);
    }
}

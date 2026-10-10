<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * What the site's emails look like and say, editable in the admin panel
 * (Mẫu email).
 *
 * Two layers:
 * - the layout every email shares: logo, colour, greeting, signature, footer;
 * - the text of each kind of message: subject, body and button label, with
 *   {placeholders} filled in when the message is sent.
 *
 * For the marketplace notifications the same subject and body also go to the
 * bell in the site header. Anything not changed by an admin uses the defaults
 * in TYPES.
 */
class EmailTemplate
{
    public const LAYOUT_KEY = 'email_layout';

    public const CONTENT_KEY = 'email_contents';

    private const DEFAULT_BUTTON = 'Xem chi tiết';

    private const LAYOUT_DEFAULTS = [
        'show_logo' => true,
        'brand_color' => '#059669',
        'greeting' => 'Xin chào {ten},',
        'signature' => "Trân trọng,\n{site}",
        'footer' => "© {nam} {site}\nBạn nhận được email này vì có tài khoản tại {site}.",
    ];

    /** Placeholders the layout texts understand. */
    public const LAYOUT_VARIABLES = [
        'ten' => 'tên người nhận',
        'site' => 'tên website',
        'nam' => 'năm hiện tại',
        'email_lien_he' => 'email liên hệ (đặt ở trang Cài đặt)',
    ];

    /**
     * Every kind of message: its name in the admin panel, default texts, the
     * placeholders it understands, and sample values for the preview.
     *
     * @var array<string, array{label: string, group: string, subject: string, body: string, button?: string, vars: array<string, string>, sample: array<string, string>}>
     */
    public const TYPES = [
        'outbid' => [
            'label' => 'Bị vượt giá',
            'group' => 'Đấu giá',
            'subject' => 'Bạn đã bị vượt giá',
            'body' => 'Có người vừa đặt {gia} cho "{tin}". Hãy đặt giá cao hơn nếu bạn vẫn muốn thắng.',
            'vars' => ['tin' => 'tiêu đề tin', 'gia' => 'giá vừa được đặt'],
            'sample' => ['tin' => 'iPhone 14 Pro 256GB tím', 'gia' => '12.600.000 đ'],
        ],
        'won' => [
            'label' => 'Thắng phiên đấu giá',
            'group' => 'Đấu giá',
            'subject' => 'Bạn đã thắng phiên đấu giá',
            'body' => 'Bạn thắng "{tin}" với giá {gia}. Mở trang phiên để xem thông tin liên hệ của người bán.',
            'vars' => ['tin' => 'tiêu đề tin', 'gia' => 'giá chốt'],
            'sample' => ['tin' => 'iPhone 14 Pro 256GB tím', 'gia' => '13.200.000 đ'],
        ],
        'lost' => [
            'label' => 'Không thắng phiên',
            'group' => 'Đấu giá',
            'subject' => 'Phiên đấu giá đã kết thúc',
            'body' => 'Phiên "{tin}" đã kết thúc và bạn không phải là người thắng.',
            'vars' => ['tin' => 'tiêu đề tin'],
            'sample' => ['tin' => 'iPhone 14 Pro 256GB tím'],
        ],
        'sold' => [
            'label' => 'Phiên của người bán có người thắng',
            'group' => 'Đấu giá',
            'subject' => 'Phiên của bạn đã có người thắng',
            'body' => '"{tin}" được chốt ở giá {gia} cho {nguoi_thang}. Mở trang phiên để xem thông tin liên hệ của người thắng.',
            'vars' => ['tin' => 'tiêu đề tin', 'gia' => 'giá chốt', 'nguoi_thang' => 'tên người thắng'],
            'sample' => ['tin' => 'iPhone 14 Pro 256GB tím', 'gia' => '13.200.000 đ', 'nguoi_thang' => 'Trần Thị Mai'],
        ],
        'unsold' => [
            'label' => 'Phiên kết thúc không có người thắng',
            'group' => 'Đấu giá',
            'subject' => 'Phiên của bạn kết thúc không có người thắng',
            'body' => '"{tin}" đã kết thúc mà không có lượt đặt nào đạt yêu cầu. Bạn có thể đăng lại từ trang phiên đấu giá của tôi.',
            'vars' => ['tin' => 'tiêu đề tin'],
            'sample' => ['tin' => 'Đồng hồ Seiko 5 Sports'],
        ],
        'cancelled' => [
            'label' => 'Phiên bị huỷ',
            'group' => 'Đấu giá',
            'subject' => 'Phiên đấu giá đã bị huỷ',
            'body' => 'Phiên "{tin}" mà bạn tham gia đã bị huỷ.',
            'vars' => ['tin' => 'tiêu đề tin'],
            'sample' => ['tin' => 'Đồng hồ Seiko 5 Sports'],
        ],
        'winner_withdrew' => [
            'label' => 'Người thắng từ bỏ, có người kế tiếp',
            'group' => 'Đấu giá',
            'subject' => 'Người thắng đã từ bỏ kết quả',
            'body' => 'Người thắng "{tin}" đã từ bỏ. Người đặt giá cao kế tiếp trở thành người thắng.',
            'vars' => ['tin' => 'tiêu đề tin'],
            'sample' => ['tin' => 'MacBook Air M2 13 inch'],
        ],
        'winner_withdrew_final' => [
            'label' => 'Người thắng từ bỏ, không còn ai kế tiếp',
            'group' => 'Đấu giá',
            'subject' => 'Người thắng đã từ bỏ kết quả',
            'body' => 'Người thắng "{tin}" đã từ bỏ và không còn ai kế tiếp. Phiên đã bị huỷ, bạn có thể đăng lại.',
            'vars' => ['tin' => 'tiêu đề tin'],
            'sample' => ['tin' => 'MacBook Air M2 13 inch'],
        ],
        'forfeit_warning' => [
            'label' => 'Cảnh báo sau khi từ bỏ kết quả',
            'group' => 'Đấu giá',
            'subject' => 'Bạn đã từ bỏ một kết quả đấu giá',
            'body' => 'Kết quả phiên "{tin}" đã được chuyển cho người khác. {canh_bao}',
            'vars' => ['tin' => 'tiêu đề tin', 'canh_bao' => 'câu báo mức khoá của lần từ bỏ kế tiếp (có thể trống)'],
            'sample' => ['tin' => 'MacBook Air M2 13 inch', 'canh_bao' => 'Nếu từ bỏ thêm lần nữa, bạn sẽ bị tạm khoá đặt giá 7 ngày.'],
        ],
        'bid_ban' => [
            'label' => 'Bị tạm khoá đặt giá',
            'group' => 'Đấu giá',
            'subject' => 'Bạn bị tạm khoá đặt giá',
            'body' => 'Bạn đã từ bỏ kết quả đấu giá {so_lan} lần gần đây (lần này: "{tin}"). Bạn không thể đặt giá cho tới {den}.',
            'vars' => ['tin' => 'tiêu đề tin', 'so_lan' => 'số lần đã từ bỏ', 'den' => 'thời điểm hết khoá'],
            'sample' => ['tin' => 'MacBook Air M2 13 inch', 'so_lan' => '2', 'den' => '21:00 17/10/2026'],
        ],
        'expiring' => [
            'label' => 'Tin sắp hết hạn',
            'group' => 'Tin đăng',
            'subject' => 'Tin đăng của bạn sắp hết hạn',
            'body' => 'Tin "{tin}" sẽ hết hạn lúc {het_han}. Bấm "Gia hạn" ở trang tin để tiếp tục hiển thị.',
            'vars' => ['tin' => 'tiêu đề tin', 'het_han' => 'thời điểm hết hạn'],
            'sample' => ['tin' => 'Bàn làm việc gỗ sồi 1m2', 'het_han' => '14:52 09/11/2026'],
        ],
        'expired' => [
            'label' => 'Tin đã hết hạn',
            'group' => 'Tin đăng',
            'subject' => 'Tin đăng của bạn đã hết hạn',
            'body' => 'Tin "{tin}" đã hết thời gian hiển thị. Bạn có thể gia hạn hoặc đăng lại từ trang tin đăng của tôi.',
            'vars' => ['tin' => 'tiêu đề tin'],
            'sample' => ['tin' => 'Bàn làm việc gỗ sồi 1m2'],
        ],
        'moderation' => [
            'label' => 'Kết quả kiểm duyệt tin',
            'group' => 'Tin đăng',
            'subject' => 'Tin đăng của bạn: {trang_thai}',
            'body' => 'Tin "{tin}" {trang_thai}. {ghi_chu}',
            'vars' => ['tin' => 'tiêu đề tin', 'trang_thai' => 'đã duyệt / bị từ chối / bị ẩn…', 'ghi_chu' => 'ghi chú của kiểm duyệt viên (có thể trống)'],
            'sample' => ['tin' => 'Bàn làm việc gỗ sồi 1m2', 'trang_thai' => 'bị từ chối', 'ghi_chu' => 'Ghi chú của kiểm duyệt viên: Ảnh mờ, vui lòng chụp lại.'],
        ],
        'saved_search' => [
            'label' => 'Có tin mới khớp tìm kiếm đã lưu',
            'group' => 'Tin đăng',
            'subject' => 'Có tin mới khớp tìm kiếm của bạn',
            'body' => '"{tin}" vừa được đăng, khớp với tìm kiếm đã lưu: {tim_kiem}.',
            'vars' => ['tin' => 'tiêu đề tin', 'tim_kiem' => 'mô tả tìm kiếm đã lưu'],
            'sample' => ['tin' => 'Xe đạp địa hình Giant ATX 720', 'tim_kiem' => '"xe đạp" · Đà Nẵng'],
        ],
        'giveaway_new' => [
            'label' => 'Chương trình quà tặng mới',
            'group' => 'Quà tặng',
            'subject' => 'Quà tặng mới: {chuong_trinh}',
            'body' => 'Quà: {qua}. Đăng ký tham gia miễn phí bằng mã số thành viên của bạn trước {han_dang_ky}.',
            'vars' => ['chuong_trinh' => 'tên chương trình', 'qua' => 'phần quà', 'han_dang_ky' => 'hạn đăng ký'],
            'sample' => ['chuong_trinh' => 'Tặng áo thun Chốt Giá', 'qua' => '5 áo thun', 'han_dang_ky' => '21:00 31/10/2026'],
        ],
        'giveaway_won' => [
            'label' => 'Trúng quà tặng',
            'group' => 'Quà tặng',
            'subject' => 'Bạn đã trúng quà tặng',
            'body' => 'Mã số của bạn đã được chọn trong chương trình "{chuong_trinh}". Quà: {qua}.',
            'vars' => ['chuong_trinh' => 'tên chương trình', 'qua' => 'phần quà'],
            'sample' => ['chuong_trinh' => 'Tặng áo thun Chốt Giá', 'qua' => '5 áo thun'],
        ],
        'verify_email' => [
            'label' => 'Xác thực email khi đăng ký',
            'group' => 'Tài khoản',
            'subject' => 'Xác thực địa chỉ email',
            'body' => "Hãy bấm nút bên dưới để xác thực địa chỉ email của bạn.\nNếu bạn không tạo tài khoản tại {site}, hãy bỏ qua email này.",
            'button' => 'Xác thực email',
            'vars' => [],
            'sample' => [],
        ],
        'reset_password' => [
            'label' => 'Đặt lại mật khẩu',
            'group' => 'Tài khoản',
            'subject' => 'Đặt lại mật khẩu',
            'body' => "Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.\nLiên kết đặt lại mật khẩu sẽ hết hạn sau {phut} phút.\nNếu bạn không yêu cầu, hãy bỏ qua email này; mật khẩu của bạn không thay đổi.",
            'button' => 'Đặt lại mật khẩu',
            'vars' => ['phut' => 'số phút liên kết còn hiệu lực'],
            'sample' => ['phut' => '60'],
        ],
    ];

    /** The two account emails go by email only; the rest also show on the bell. */
    public const MAIL_ONLY = ['verify_email', 'reset_password'];

    // ---- layout -----------------------------------------------------------

    /**
     * @return array{show_logo: bool, brand_color: string, greeting: string, signature: string, footer: string}
     */
    public static function layout(): array
    {
        $stored = Setting::read(self::LAYOUT_KEY);
        $stored = is_array($stored) ? $stored : [];
        $color = (string) ($stored['brand_color'] ?? '');

        return [
            'show_logo' => (bool) ($stored['show_logo'] ?? self::LAYOUT_DEFAULTS['show_logo']),
            'brand_color' => self::isColor($color) ? $color : self::LAYOUT_DEFAULTS['brand_color'],
            'greeting' => self::textOr($stored['greeting'] ?? null, self::LAYOUT_DEFAULTS['greeting']),
            'signature' => self::textOr($stored['signature'] ?? null, self::LAYOUT_DEFAULTS['signature']),
            'footer' => self::textOr($stored['footer'] ?? null, self::LAYOUT_DEFAULTS['footer']),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function saveLayout(array $values): void
    {
        Setting::write(self::LAYOUT_KEY, [
            'show_logo' => (bool) ($values['show_logo'] ?? true),
            'brand_color' => (string) ($values['brand_color'] ?? ''),
            'greeting' => trim((string) ($values['greeting'] ?? '')),
            'signature' => trim((string) ($values['signature'] ?? '')),
            'footer' => trim((string) ($values['footer'] ?? '')),
        ]);
    }

    public static function resetLayout(): void
    {
        Setting::forget(self::LAYOUT_KEY);
    }

    public static function isColor(string $value): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    // ---- per-type texts ---------------------------------------------------

    /**
     * The texts in force for a type: the admin's, or the defaults.
     *
     * @return array{subject: string, body: string, button: string, customized: bool}
     */
    public static function content(string $type): array
    {
        $default = self::TYPES[$type];
        $stored = Setting::read(self::CONTENT_KEY);
        $stored = is_array($stored) && is_array($stored[$type] ?? null) ? $stored[$type] : [];

        return [
            'subject' => self::textOr($stored['subject'] ?? null, $default['subject']),
            'body' => self::textOr($stored['body'] ?? null, $default['body']),
            'button' => self::textOr($stored['button'] ?? null, $default['button'] ?? self::DEFAULT_BUTTON),
            'customized' => $stored !== [],
        ];
    }

    public static function saveContent(string $type, string $subject, string $body, string $button): void
    {
        $all = Setting::read(self::CONTENT_KEY);
        $all = is_array($all) ? $all : [];
        $all[$type] = ['subject' => trim($subject), 'body' => trim($body), 'button' => trim($button)];

        Setting::write(self::CONTENT_KEY, $all);
    }

    public static function resetContent(string $type): void
    {
        $all = Setting::read(self::CONTENT_KEY);
        $all = is_array($all) ? $all : [];
        unset($all[$type]);

        $all === [] ? Setting::forget(self::CONTENT_KEY) : Setting::write(self::CONTENT_KEY, $all);
    }

    /**
     * Subject and body of a type with the placeholders filled in.
     *
     * @param  array<string, string|int|null>  $variables
     * @return array{subject: string, body: string}
     */
    public static function render(string $type, array $variables = []): array
    {
        $content = self::content($type);

        return [
            'subject' => self::fill($content['subject'], $variables),
            'body' => self::fill($content['body'], $variables),
        ];
    }

    // ---- building the email ------------------------------------------------

    /**
     * An email in the site's layout, greeting and signature in place. The
     * caller adds the subject, the paragraphs and the button.
     */
    public static function message(?string $recipientName = null): MailMessage
    {
        $layout = self::layout();
        $variables = ['ten' => $recipientName ?: 'bạn'];

        return (new MailMessage)
            ->view(['emails.message', 'emails.message-text'], [
                'brandColor' => $layout['brand_color'],
                'buttonTextColor' => self::readableOn($layout['brand_color']),
                'logoUrl' => $layout['show_logo'] ? asset('apple-touch-icon.png') : null,
                'siteName' => (string) config('app.name'),
                'siteUrl' => url('/'),
                'signatureLines' => self::lines(self::fill($layout['signature'], $variables)),
                'footerLines' => self::lines(self::fill($layout['footer'], $variables)),
            ])
            ->greeting(self::fill($layout['greeting'], $variables));
    }

    /**
     * The whole email of a type: layout, texts, and the button when there is a link.
     *
     * @param  array<string, string|int|null>  $variables
     */
    public static function compose(string $type, array $variables, ?string $recipientName, ?string $url = null): MailMessage
    {
        $text = self::render($type, $variables);

        return self::withBody(self::message($recipientName)->subject($text['subject']), $text['body'], $type, $url);
    }

    /**
     * Add an already written body: one paragraph per line, then the type's button.
     */
    public static function withBody(MailMessage $message, string $body, ?string $type, ?string $url): MailMessage
    {
        foreach (self::lines($body) as $paragraph) {
            $message->line($paragraph);
        }

        if ($url !== null) {
            $message->action($type !== null && isset(self::TYPES[$type]) ? self::content($type)['button'] : self::DEFAULT_BUTTON, $url);
        }

        return $message;
    }

    /**
     * The email of a type filled with sample values, for the preview and the test send.
     */
    public static function sample(string $type, ?string $recipientName = null): MailMessage
    {
        return self::compose($type, self::TYPES[$type]['sample'], $recipientName ?: 'Nguyễn Văn An', url('/'));
    }

    // ---- helpers ------------------------------------------------------------

    /**
     * Replace {placeholders}. Unknown ones are left as written, so a typo
     * shows in the preview instead of silently vanishing. Blank values leave
     * no double spaces behind.
     *
     * @param  array<string, string|int|null>  $variables
     */
    public static function fill(string $text, array $variables = []): string
    {
        $variables += [
            'site' => (string) config('app.name'),
            'nam' => date('Y'),
            'email_lien_he' => MarketplaceSettings::contactEmail(),
        ];

        $replace = [];

        foreach ($variables as $name => $value) {
            $replace['{'.$name.'}'] = (string) $value;
        }

        $lines = array_map(
            fn (string $line) => trim((string) preg_replace('/[ \t]{2,}/', ' ', $line)),
            explode("\n", str_replace("\r", '', strtr($text, $replace))),
        );

        return trim(implode("\n", $lines));
    }

    /**
     * @return list<string> the non-empty lines of a text
     */
    public static function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $text)), fn (string $line) => $line !== ''));
    }

    private static function textOr(mixed $value, string $default): string
    {
        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    /**
     * White or near-black, whichever reads better on the given colour.
     */
    private static function readableOn(string $hex): string
    {
        [$r, $g, $b] = array_map(fn (string $pair) => hexdec($pair), str_split(ltrim($hex, '#'), 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#111827' : '#ffffff';
    }
}

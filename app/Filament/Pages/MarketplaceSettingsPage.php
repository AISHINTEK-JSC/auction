<?php

namespace App\Filament\Pages;

use App\Actions\Listings\CreateListing;
use App\Models\Setting;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\Sms\SmsSender;
use App\Support\AdminLog;
use App\Support\IntegrationSettings;
use App\Support\MarketplaceSettings;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * What an admin can change without touching code or .env: the rules of the
 * marketplace (listing lifetime, forfeit penalties, moderation, password
 * policy, contact address) and three connections: how email goes out (SMTP
 * or Amazon SES), how text messages go out (Amazon SNS) and the auction
 * engine (see App\Support\IntegrationSettings).
 */
class MarketplaceSettingsPage extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static ?string $slug = 'settings';

    protected static ?string $title = 'Cài đặt';

    protected static ?string $navigationLabel = 'Cài đặt';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['groups' => $this->groups()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Chỉnh sửa cài đặt')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modalSubmitActionLabel('Lưu')
                ->fillForm(fn (): array => [
                    ...MarketplaceSettings::all(),
                    'listing_approval' => CreateListing::approvalRequired(),
                    ...Setting::passwordPolicy(),
                    // The stored address only: blank while the default one is in use.
                    'contact_email' => (string) Setting::read(MarketplaceSettings::CONTACT_EMAIL, ''),
                ])
                ->schema([
                    TextInput::make('contact_email')->label('Email liên hệ')
                        ->helperText('Hiện ở trang Điều khoản và Chính sách quyền riêng tư. Để trống thì dùng địa chỉ mặc định: '.config('marketplace.contact_email').'.')
                        ->email()->maxLength(255),
                    TextInput::make('fixed_listing_days')->label('Tin giá cố định hiển thị (ngày)')
                        ->integer()->minValue(1)->maxValue(365)->required(),
                    TextInput::make('expiry_reminder_days')->label('Nhắc người bán trước khi hết hạn (ngày)')
                        ->helperText('0 để không nhắc.')
                        ->integer()->minValue(0)->maxValue(60)->required(),
                    Toggle::make('listing_approval')->label('Duyệt tin trước khi hiển thị')
                        ->helperText('Khi bật, tin mới chờ kiểm duyệt viên duyệt rồi mới hiển thị; phiên đấu giá chỉ được mở lúc duyệt.'),
                    TextInput::make('forfeit_window_days')->label('Tính các lần từ bỏ kết quả trong (ngày)')
                        ->integer()->minValue(1)->maxValue(730)->required(),
                    TextInput::make('forfeit_ban_second_days')->label('Lần từ bỏ thứ 2: khoá đặt giá (ngày)')
                        ->helperText('Lần đầu luôn chỉ cảnh báo. 0 để không khoá ở lần này.')
                        ->integer()->minValue(0)->maxValue(365)->required(),
                    TextInput::make('forfeit_ban_repeat_days')->label('Từ lần thứ 3: khoá đặt giá (ngày)')
                        ->integer()->minValue(0)->maxValue(365)->required(),
                    TextInput::make('min_length')->label('Mật khẩu: độ dài tối thiểu')
                        ->integer()->minValue(6)->maxValue(64)->required(),
                    Toggle::make('mixed_case')->label('Mật khẩu: bắt buộc có chữ hoa và chữ thường'),
                    Toggle::make('numbers')->label('Mật khẩu: bắt buộc có chữ số'),
                    Toggle::make('symbols')->label('Mật khẩu: bắt buộc có ký tự đặc biệt'),
                ])
                ->action(function (array $data): void {
                    $before = $this->flat();

                    MarketplaceSettings::save(array_map('intval', array_intersect_key($data, MarketplaceSettings::all())));
                    MarketplaceSettings::saveContactEmail($data['contact_email'] ?? null);
                    Setting::write(Setting::LISTING_APPROVAL, (bool) $data['listing_approval']);
                    Setting::write(Setting::PASSWORD_POLICY, [
                        'min_length' => (int) $data['min_length'],
                        'mixed_case' => (bool) $data['mixed_case'],
                        'numbers' => (bool) $data['numbers'],
                        'symbols' => (bool) $data['symbols'],
                    ]);

                    $this->logChanges($before);
                }),
            $this->smtpAction(),
            $this->smsAction(),
            $this->engineAction(),
            ActionGroup::make([$this->testMailAction(), $this->testSmsAction(), $this->testEngineAction()])
                ->label('Kiểm tra kết nối')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * How email goes out: an SMTP server or Amazon SES. Passwords and secret
     * keys are never shown: leaving their field empty keeps them.
     */
    private function smtpAction(): Action
    {
        return Action::make('smtp')
            ->label('Email (SMTP / SES)')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->modalHeading('Gửi email')
            ->modalSubmitActionLabel('Lưu')
            ->modalDescription('Ô nào để trống thì dùng giá trị trong tệp .env. Lưu xong nên bấm "Kiểm tra kết nối" → "Gửi email thử".')
            ->fillForm(fn (): array => [
                ...array_diff_key(IntegrationSettings::stored(IntegrationSettings::SMTP), ['password' => true]),
                'ses_key' => IntegrationSettings::stored(IntegrationSettings::SES)['key'] ?? null,
                'ses_region' => IntegrationSettings::stored(IntegrationSettings::SES)['region'] ?? null,
                'reset' => false,
            ])
            ->schema([
                Select::make('mailer')->label('Gửi email qua')
                    ->options(IntegrationSettings::MAILERS)
                    ->placeholder('Theo .env (MAIL_MAILER)')
                    ->helperText('Để trống mà có nhập máy chủ SMTP bên dưới thì gửi qua SMTP.'),
                TextInput::make('from_address')->label('Địa chỉ người gửi')->email()->maxLength(255)
                    ->helperText('Với Amazon SES, địa chỉ hoặc tên miền này phải được xác minh (verified identity) trong SES.'),
                TextInput::make('from_name')->label('Tên người gửi')->maxLength(255),
                Section::make('SMTP')->compact()->schema([
                    TextInput::make('host')->label('Máy chủ')->placeholder('smtp.example.com')->maxLength(255),
                    TextInput::make('port')->label('Cổng')->integer()->minValue(1)->maxValue(65535)->placeholder('587'),
                    Select::make('scheme')->label('Mã hoá')
                        ->options(['smtp' => 'STARTTLS / tự động (cổng 587, 25)', 'smtps' => 'SSL/TLS (cổng 465)'])
                        ->placeholder('Theo .env'),
                    TextInput::make('username')->label('Tài khoản')->maxLength(255)->autocomplete('off'),
                    TextInput::make('password')->label('Mật khẩu')->password()->revealable()->maxLength(255)->autocomplete('new-password')
                        ->helperText($this->secretHelp(IntegrationSettings::SMTP, 'password')),
                ]),
                Section::make('Amazon SES')->compact()
                    ->description('Để trống access key và secret key thì dùng IAM role của máy chủ.')
                    ->schema($this->awsFields('ses_', IntegrationSettings::SES, 'ses:SendRawEmail')),
                Toggle::make('reset')->label('Bỏ toàn bộ cấu hình đã lưu ở đây, dùng lại .env')
                    ->helperText('Khi bật, các ô phía trên bị bỏ qua và mật khẩu, secret key đã lưu bị xoá.'),
            ])
            ->action(function (array $data): void {
                $before = $this->flat();
                $extra = [];

                if ($data['reset']) {
                    IntegrationSettings::reset(IntegrationSettings::SMTP);
                    IntegrationSettings::reset(IntegrationSettings::SES);
                } else {
                    $smtp = IntegrationSettings::save(IntegrationSettings::SMTP, $data);
                    $ses = IntegrationSettings::save(IntegrationSettings::SES, $this->unprefixed('ses_', $data));
                    $extra = [
                        ...(in_array('password', $smtp, true) ? ['Mật khẩu SMTP: đã đổi'] : []),
                        ...(in_array('secret', $ses, true) ? ['Secret key SES: đã đổi'] : []),
                    ];
                }

                $this->logChanges($before, $extra);
            });
    }

    /**
     * How text messages (the phone number codes) go out. The secret key is
     * never shown: leaving its field empty keeps it.
     */
    private function smsAction(): Action
    {
        return Action::make('sms')
            ->label('SMS (SNS)')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->modalHeading('Gửi SMS (mã xác thực số điện thoại)')
            ->modalSubmitActionLabel('Lưu')
            ->modalDescription('Ô nào để trống thì dùng giá trị trong tệp .env. Tài khoản AWS phải đã ra khỏi SMS sandbox và có hạn mức chi tiêu SMS đủ dùng. Lưu xong nên bấm "Kiểm tra kết nối" → "Gửi SMS thử".')
            ->fillForm(fn (): array => [
                ...array_diff_key(IntegrationSettings::stored(IntegrationSettings::SMS), ['secret' => true]),
                'reset' => false,
            ])
            ->schema([
                Select::make('driver')->label('Gửi SMS qua')
                    ->options(IntegrationSettings::SMS_DRIVERS)
                    ->placeholder('Theo .env (SMS_DRIVER)'),
                ...$this->awsFields('', IntegrationSettings::SMS, 'sns:Publish'),
                TextInput::make('sender_id')->label('Sender ID (brandname)')
                    ->helperText('Tên thương hiệu đã đăng ký với nhà mạng, tối đa 11 ký tự chữ và số. Để trống thì nhà mạng dùng số mặc định.')
                    ->regex('/^[A-Za-z0-9]{1,11}$/')->maxLength(11),
                Toggle::make('reset')->label('Bỏ toàn bộ cấu hình đã lưu ở đây, dùng lại .env')
                    ->helperText('Khi bật, các ô phía trên bị bỏ qua và secret key đã lưu bị xoá.'),
            ])
            ->action(function (array $data): void {
                $before = $this->flat();

                $replaced = $data['reset']
                    ? tap([], fn () => IntegrationSettings::reset(IntegrationSettings::SMS))
                    : IntegrationSettings::save(IntegrationSettings::SMS, $data);

                $this->logChanges($before, in_array('secret', $replaced, true) ? ['Secret key SNS: đã đổi'] : []);
            });
    }

    /**
     * Access key, secret key and region of an AWS service.
     *
     * @return list<TextInput>
     */
    private function awsFields(string $prefix, string $group, string $permission): array
    {
        return [
            TextInput::make($prefix.'key')->label('Access key ID')->maxLength(128)->autocomplete('off')
                ->helperText("Tài khoản IAM cần quyền {$permission}."),
            TextInput::make($prefix.'secret')->label('Secret access key')->password()->revealable()->maxLength(128)->autocomplete('new-password')
                ->helperText($this->secretHelp($group, 'secret')),
            TextInput::make($prefix.'region')->label('Vùng (region)')->placeholder('ap-southeast-2')
                ->regex('/^[a-z]{2}(-[a-z]+)+-\d$/')->maxLength(32),
        ];
    }

    /**
     * The fields of a form whose names start with a prefix, the prefix taken off.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function unprefixed(string $prefix, array $data): array
    {
        $fields = [];

        foreach ($data as $field => $value) {
            if (str_starts_with($field, $prefix)) {
                $fields[substr($field, strlen($prefix))] = $value;
            }
        }

        return $fields;
    }

    /**
     * The auction engine. Token and push key are never shown: an empty field keeps them.
     */
    private function engineAction(): Action
    {
        return Action::make('engine')
            ->label('Auction engine')
            ->icon(Heroicon::OutlinedServerStack)
            ->color('gray')
            ->modalHeading('Kết nối tới auction engine')
            ->modalSubmitActionLabel('Lưu')
            ->modalDescription('Ô nào để trống thì dùng giá trị trong tệp .env. Sai địa chỉ hoặc token thì không đăng được phiên và không đặt được giá: lưu xong hãy bấm "Kiểm tra kết nối" → "Kiểm tra engine".')
            ->fillForm(fn (): array => [
                'url' => IntegrationSettings::stored(IntegrationSettings::ENGINE)['url'] ?? null,
                'reset' => false,
            ])
            ->schema([
                TextInput::make('url')->label('Địa chỉ API')->url()->maxLength(255)->placeholder('https://engine.example.com/api/v1'),
                TextInput::make('token')->label('API token')->password()->revealable()->maxLength(2000)->autocomplete('new-password')
                    ->helperText('App gửi token này cho engine. '.$this->secretHelp(IntegrationSettings::ENGINE, 'token')),
                TextInput::make('push_key')->label('Push key')->password()->revealable()->maxLength(2000)->autocomplete('new-password')
                    ->helperText('Engine gửi khoá này khi báo sự kiện về '.route('webhooks.auction').'. '.$this->secretHelp(IntegrationSettings::ENGINE, 'push_key')),
                Toggle::make('reset')->label('Bỏ toàn bộ cấu hình đã lưu ở đây, dùng lại .env')
                    ->helperText('Khi bật, các ô phía trên bị bỏ qua và token, push key đã lưu bị xoá.'),
            ])
            ->action(function (array $data): void {
                $before = $this->flat();

                $replaced = $data['reset']
                    ? tap([], fn () => IntegrationSettings::reset(IntegrationSettings::ENGINE))
                    : IntegrationSettings::save(IntegrationSettings::ENGINE, $data);

                $this->logChanges($before, array_map(
                    fn (string $field) => ($field === 'token' ? 'API token' : 'Push key').' của engine: đã đổi',
                    $replaced,
                ));
            });
    }

    /**
     * Send one message through the mail server now in force, and say what happened.
     */
    private function testMailAction(): Action
    {
        return Action::make('testMail')
            ->label('Gửi email thử')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->modalHeading('Gửi email thử')
            ->modalSubmitActionLabel('Gửi')
            ->fillForm(fn (): array => ['to' => auth()->user()?->email])
            ->schema([TextInput::make('to')->label('Gửi tới')->email()->required()])
            ->action(function (array $data): void {
                try {
                    Mail::raw(
                        'Đây là email thử từ trang quản trị '.config('app.name').'. Nếu bạn đọc được thư này, cấu hình gửi email đang hoạt động.',
                        fn ($message) => $message->to($data['to'])->subject('Email thử từ '.config('app.name')),
                    );
                } catch (Throwable $e) {
                    Notification::make()->title('Không gửi được email')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Đã gửi email thử tới '.$data['to'])
                    ->body('Gửi qua '.$this->mailServer().'.')->success()->send();
            });
    }

    /**
     * Text one message through the SMS driver now in force, and say what happened.
     */
    private function testSmsAction(): Action
    {
        return Action::make('testSms')
            ->label('Gửi SMS thử')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->modalHeading('Gửi SMS thử')
            ->modalSubmitActionLabel('Gửi')
            ->fillForm(fn (): array => ['to' => auth()->user()?->phone])
            ->schema([TextInput::make('to')->label('Gửi tới số')->tel()->required()->rule(PhoneNumber::RULE)])
            ->action(function (array $data, SmsSender $sms): void {
                $to = PhoneNumber::international($data['to']);

                try {
                    $sms->send($to, 'Day la tin nhan thu tu trang quan tri '.Str::ascii((string) config('app.name')).'. Cau hinh gui SMS dang hoat dong.');
                } catch (Throwable $e) {
                    Notification::make()->title('Không gửi được SMS')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Đã gửi SMS thử tới '.$to)
                    ->body('Gửi qua '.$this->smsGateway().'.')->success()->send();
            });
    }

    /**
     * Ask the engine for this site's auctions: proves the address and the token.
     */
    private function testEngineAction(): Action
    {
        return Action::make('testEngine')
            ->label('Kiểm tra engine')
            ->icon(Heroicon::OutlinedServerStack)
            ->action(function (AuctionEngineClient $engine): void {
                try {
                    $auctions = $engine->listAuctions();
                } catch (Throwable $e) {
                    Notification::make()->title('Không kết nối được tới engine')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Kết nối tới engine hoạt động')
                    ->body('Engine trả về '.count($auctions).' phiên của site này.')->success()->send();
            });
    }

    /**
     * One log line naming exactly what changed, old value → new value. Secrets
     * never appear in it: their rows only say whether and where one is set.
     *
     * @param  array<string, string>  $before  the settings as shown before the change
     * @param  list<string>  $extra  changes the shown values cannot reveal (a secret replaced by another)
     */
    private function logChanges(array $before, array $extra = []): void
    {
        $changes = $extra;

        foreach ($this->flat() as $label => $value) {
            if (($before[$label] ?? null) !== $value) {
                $changes[] = "{$label}: ".($before[$label] ?? '—')." → {$value}";
            }
        }

        if ($changes !== []) {
            AdminLog::record('settings.updated', null, 'Đổi cài đặt — '.implode('; ', $changes));
        }

        Notification::make()->title($changes === [] ? 'Không có gì thay đổi' : 'Đã lưu cài đặt')->success()->send();
    }

    private function secretHelp(string $group, string $field): string
    {
        return match (IntegrationSettings::source($group, $field)) {
            'admin' => 'Đã có giá trị lưu ở đây. Để trống để giữ nguyên.',
            'env' => 'Đang dùng giá trị trong .env. Để trống để giữ nguyên.',
            default => 'Chưa đặt.',
        };
    }

    private function secretStatus(string $group, string $field): string
    {
        return match (IntegrationSettings::source($group, $field)) {
            'admin' => 'Đã đặt (trang quản trị)',
            'env' => 'Đã đặt (.env)',
            default => 'Chưa đặt',
        };
    }

    /**
     * Whether a group runs on what was saved here, on .env, or on a mix.
     */
    private function origin(string $group): string
    {
        $stored = count(IntegrationSettings::stored($group));

        return match (true) {
            $stored === 0 => 'Tệp .env',
            default => 'Trang quản trị ('.$stored.' mục), còn lại từ .env',
        };
    }

    private function mailServer(): string
    {
        return match (config('mail.default')) {
            'smtp' => config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'),
            'ses', 'ses-v2' => 'Amazon SES ('.config('services.ses.region').')',
            default => 'mailer "'.config('mail.default').'" (không gửi thật)',
        };
    }

    private function smsGateway(): string
    {
        return match (config('services.sms.driver')) {
            'sns' => 'Amazon SNS ('.config('services.sns.region').')',
            'log' => 'log (không gửi thật, mã nằm trong storage/logs)',
            default => 'driver "'.config('services.sms.driver').'"',
        };
    }

    /**
     * An access key ID is not a secret, but there is no need to show all of it.
     */
    private function maskedKey(mixed $key): string
    {
        return filled($key) ? substr((string) $key, 0, 4).'…'.substr((string) $key, -4) : 'Không dùng (IAM role)';
    }

    /**
     * The settings as shown on the page, grouped.
     *
     * @return array<string, array<string, string>>
     */
    private function groups(): array
    {
        $settings = MarketplaceSettings::all();
        $policy = Setting::passwordPolicy();
        $days = fn (int $value, string $zero = '0 ngày') => $value === 0 ? $zero : "{$value} ngày";
        $yesNo = fn (bool $value) => $value ? 'Có' : 'Không';

        return [
            'Liên hệ' => [
                'Email liên hệ' => MarketplaceSettings::contactEmail(),
            ],
            'Tin đăng' => [
                'Tin giá cố định hiển thị' => $days($settings['fixed_listing_days']),
                'Nhắc người bán trước khi hết hạn' => $days($settings['expiry_reminder_days'], 'Không nhắc'),
                'Duyệt tin trước khi hiển thị' => CreateListing::approvalRequired() ? 'Bật' : 'Tắt',
            ],
            'Người thắng từ bỏ kết quả' => [
                'Tính các lần từ bỏ trong' => $days($settings['forfeit_window_days']),
                'Lần thứ 1' => 'Cảnh báo',
                'Lần thứ 2: khoá đặt giá' => $days($settings['forfeit_ban_second_days'], 'Không khoá'),
                'Từ lần thứ 3: khoá đặt giá' => $days($settings['forfeit_ban_repeat_days'], 'Không khoá'),
            ],
            'Mật khẩu' => [
                'Độ dài tối thiểu' => $policy['min_length'].' ký tự',
                'Chữ hoa và chữ thường' => $yesNo($policy['mixed_case']),
                'Chữ số' => $yesNo($policy['numbers']),
                'Ký tự đặc biệt' => $yesNo($policy['symbols']),
            ],
            'Email' => [
                'Máy chủ gửi thư' => $this->mailServer(),
                'Mã hoá' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'SSL/TLS' : 'STARTTLS / tự động',
                'Tài khoản SMTP' => (string) (config('mail.mailers.smtp.username') ?: 'Không dùng'),
                'Mật khẩu SMTP' => $this->secretStatus(IntegrationSettings::SMTP, 'password'),
                'SES access key' => $this->maskedKey(config('services.ses.key')),
                'SES secret key' => $this->secretStatus(IntegrationSettings::SES, 'secret'),
                'Người gửi' => config('mail.from.name').' <'.config('mail.from.address').'>',
                'Cấu hình email lấy từ' => $this->origin(IntegrationSettings::SMTP),
                'Cấu hình SES lấy từ' => $this->origin(IntegrationSettings::SES),
            ],
            'SMS' => [
                'Gửi SMS qua' => $this->smsGateway(),
                'SNS access key' => $this->maskedKey(config('services.sns.key')),
                'SNS secret key' => $this->secretStatus(IntegrationSettings::SMS, 'secret'),
                'Sender ID' => (string) (config('services.sns.sender_id') ?: 'Mặc định của nhà mạng'),
                'Cấu hình SMS lấy từ' => $this->origin(IntegrationSettings::SMS),
            ],
            'Auction engine' => [
                'Địa chỉ API' => (string) config('services.auction_engine.url'),
                'API token' => $this->secretStatus(IntegrationSettings::ENGINE, 'token'),
                'Push key' => $this->secretStatus(IntegrationSettings::ENGINE, 'push_key'),
                'Engine báo sự kiện về' => route('webhooks.auction'),
                'Cấu hình engine lấy từ' => $this->origin(IntegrationSettings::ENGINE),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function flat(): array
    {
        return array_merge(...array_values($this->groups()));
    }
}

<?php

namespace App\Filament\Pages;

use App\Actions\Listings\CreateListing;
use App\Models\Setting;
use App\Support\AdminLog;
use App\Support\MarketplaceSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The rules of the marketplace an admin can change without touching code:
 * listing lifetime, forfeit penalties, moderation, the password policy and
 * the contact address.
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

                    // One log line naming exactly what changed, old value → new value.
                    $changes = [];
                    foreach ($this->flat() as $label => $value) {
                        if ($before[$label] !== $value) {
                            $changes[] = "{$label}: {$before[$label]} → {$value}";
                        }
                    }

                    if ($changes !== []) {
                        AdminLog::record('settings.updated', null, 'Đổi cài đặt — '.implode('; ', $changes));
                    }

                    Notification::make()->title($changes === [] ? 'Không có gì thay đổi' : 'Đã lưu cài đặt')->success()->send();
                }),
        ];
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

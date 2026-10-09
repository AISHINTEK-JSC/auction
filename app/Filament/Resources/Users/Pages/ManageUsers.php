<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Applied to registration, password reset and password change (see AppServiceProvider).
            Action::make('passwordPolicy')
                ->label('Chính sách mật khẩu')
                ->icon(Heroicon::OutlinedKey)
                ->fillForm(fn (): array => Setting::passwordPolicy())
                ->schema([
                    TextInput::make('min_length')->label('Độ dài tối thiểu')->integer()->minValue(6)->maxValue(64)->required(),
                    Toggle::make('mixed_case')->label('Bắt buộc có chữ hoa và chữ thường'),
                    Toggle::make('numbers')->label('Bắt buộc có chữ số'),
                    Toggle::make('symbols')->label('Bắt buộc có ký tự đặc biệt'),
                ])
                ->action(function (array $data): void {
                    Setting::write(Setting::PASSWORD_POLICY, [
                        'min_length' => (int) $data['min_length'],
                        'mixed_case' => (bool) $data['mixed_case'],
                        'numbers' => (bool) $data['numbers'],
                        'symbols' => (bool) $data['symbols'],
                    ]);

                    Notification::make()->title('Đã lưu chính sách mật khẩu')->success()->send();
                }),
        ];
    }
}

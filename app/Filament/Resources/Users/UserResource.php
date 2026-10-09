<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'thành viên';

    protected static ?string $pluralModelLabel = 'Thành viên';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Họ tên')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->label('Số điện thoại')->maxLength(20),
            TextInput::make('address')->label('Địa chỉ')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->label('Họ tên')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->label('SĐT')->placeholder('—'),
                IconColumn::make('email_verified_at')->label('Đã xác thực')->boolean()
                    ->getStateUsing(fn (User $record) => $record->email_verified_at !== null),
                IconColumn::make('is_admin')->label('Admin')->boolean(),
                TextColumn::make('blocked_at')->label('Khoá lúc')->dateTime('d/m/Y H:i')->placeholder('—')
                    ->description(fn (User $record) => $record->block_reason),
                TextColumn::make('created_at')->label('Tham gia')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('blocked_at')->label('Bị khoá')->nullable(),
            ])
            ->recordActions([
                Action::make('block')->label('Khoá')->icon(Heroicon::OutlinedLockClosed)->color('danger')
                    ->visible(fn (User $record) => ! $record->isBlocked() && ! $record->is(auth()->user()))
                    ->schema([
                        TextInput::make('block_reason')->label('Lý do')->maxLength(255),
                    ])
                    ->action(fn (User $record, array $data) => $record->forceFill([
                        'blocked_at' => now(),
                        'block_reason' => $data['block_reason'] ?? null,
                    ])->save()),
                Action::make('unblock')->label('Mở khoá')->icon(Heroicon::OutlinedLockOpen)
                    ->visible(fn (User $record) => $record->isBlocked())
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->forceFill(['blocked_at' => null, 'block_reason' => null])->save()),
                Action::make('toggleAdmin')
                    ->label(fn (User $record) => $record->is_admin ? 'Gỡ quyền admin' : 'Cấp quyền admin')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('gray')
                    ->visible(fn (User $record) => ! $record->is(auth()->user()))
                    ->requiresConfirmation()
                    // is_admin is deliberately not mass assignable.
                    ->action(fn (User $record) => $record->forceFill(['is_admin' => ! $record->is_admin])->save()),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}

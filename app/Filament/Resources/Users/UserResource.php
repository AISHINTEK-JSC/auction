<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\AdminLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
    use AdminOnly;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'thành viên';

    protected static ?string $pluralModelLabel = 'Thành viên';

    // Set explicitly: Filament would title-case the plural label in the menu.
    protected static ?string $navigationLabel = 'Thành viên';

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
                TextColumn::make('role')->label('Vai trò')->badge()
                    ->formatStateUsing(fn (UserRole $state) => $state->label())
                    ->color(fn (UserRole $state) => match ($state) {
                        UserRole::Admin => 'danger',
                        UserRole::Moderator => 'warning',
                        UserRole::User => 'gray',
                    }),
                TextColumn::make('member_code')->label('Mã số')->searchable(),
                TextColumn::make('blocked_at')->label('Khoá lúc')->dateTime('d/m/Y H:i')->placeholder('—')
                    ->description(fn (User $record) => $record->block_reason),
                // Times the member gave up an auction result within the penalty window.
                TextColumn::make('recent_forfeits_count')->label('Từ bỏ gần đây')
                    ->counts(['forfeits as recent_forfeits_count' => fn ($query) => $query
                        ->where('created_at', '>=', now()->subDays((int) config('marketplace.forfeit_window_days')))])
                    ->sortable(),
                TextColumn::make('bid_banned_until')->label('Cấm đặt giá tới')->dateTime('d/m/Y H:i')->placeholder('—'),
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
                    ->action(function (User $record, array $data): void {
                        $record->forceFill([
                            'blocked_at' => now(),
                            'block_reason' => $data['block_reason'] ?? null,
                        ])->save();

                        AdminLog::record('user.blocked', $record, sprintf(
                            'Khoá tài khoản %s (%s)%s',
                            $record->name,
                            $record->email,
                            $record->block_reason ? ' — lý do: '.$record->block_reason : '',
                        ));
                    }),
                Action::make('unblock')->label('Mở khoá')->icon(Heroicon::OutlinedLockOpen)
                    ->visible(fn (User $record) => $record->isBlocked())
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->forceFill(['blocked_at' => null, 'block_reason' => null])->save();

                        AdminLog::record('user.unblocked', $record, sprintf('Mở khoá tài khoản %s (%s)', $record->name, $record->email));
                    }),
                Action::make('liftBidBan')->label('Gỡ cấm đặt giá')->icon(Heroicon::OutlinedHandRaised)->color('gray')
                    ->visible(fn (User $record) => $record->isBidBanned())
                    ->requiresConfirmation()
                    ->modalDescription('Thành viên này bị tạm khoá đặt giá vì đã từ bỏ kết quả đấu giá nhiều lần. Gỡ cấm cho phép họ đặt giá lại ngay.')
                    ->action(function (User $record): void {
                        $record->forceFill(['bid_banned_until' => null])->save();

                        AdminLog::record('user.bid_ban_lifted', $record, sprintf('Gỡ cấm đặt giá cho %s (%s)', $record->name, $record->email));
                    }),
                Action::make('changeRole')->label('Đổi vai trò')->icon(Heroicon::OutlinedShieldCheck)->color('gray')
                    ->visible(fn (User $record) => ! $record->is(auth()->user()))
                    ->fillForm(fn (User $record): array => ['role' => $record->role->value])
                    ->schema([
                        Select::make('role')->label('Vai trò')->required()
                            ->options(collect(UserRole::cases())->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()])),
                    ])
                    // role is deliberately not mass assignable.
                    ->action(function (User $record, array $data): void {
                        $from = $record->role->label();
                        $record->forceFill(['role' => $data['role']])->save();

                        AdminLog::record('user.role_changed', $record, sprintf(
                            'Đổi vai trò của %s (%s): %s → %s',
                            $record->name,
                            $record->email,
                            $from,
                            $record->refresh()->role->label(),
                        ));
                    }),
                EditAction::make()->after(fn (User $record) => AdminLog::record('user.updated', $record, sprintf('Sửa thông tin thành viên %s (%s)', $record->name, $record->email))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}

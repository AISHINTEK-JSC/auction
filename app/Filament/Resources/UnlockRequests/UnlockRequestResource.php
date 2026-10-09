<?php

namespace App\Filament\Resources\UnlockRequests;

use App\Filament\Resources\UnlockRequests\Pages\ManageUnlockRequests;
use App\Models\UnlockRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UnlockRequestResource extends Resource
{
    protected static ?string $model = UnlockRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockOpen;

    protected static ?string $modelLabel = 'yêu cầu mở khoá';

    protected static ?string $pluralModelLabel = 'Yêu cầu mở khoá';

    // Set explicitly: Filament would title-case the plural label in the menu.
    protected static ?string $navigationLabel = 'Yêu cầu mở khoá';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('description')->label('Nội dung')->wrap()->placeholder('—'),
                TextColumn::make('created_at')->label('Gửi lúc')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('handled_at')->label('Xử lý lúc')->dateTime('d/m/Y H:i')->placeholder('Chưa xử lý'),
            ])
            ->filters([
                TernaryFilter::make('handled_at')->label('Đã xử lý')->nullable(),
            ])
            ->recordActions([
                Action::make('unblock')->label('Mở khoá tài khoản')->icon(Heroicon::OutlinedLockOpen)
                    ->visible(fn (UnlockRequest $record) => $record->handled_at === null)
                    ->requiresConfirmation()
                    ->action(function (UnlockRequest $record): void {
                        $user = User::query()->where('email', $record->email)->first();

                        $user?->forceFill(['blocked_at' => null, 'block_reason' => null])->save();
                        $record->update(['handled_at' => now()]);

                        Notification::make()
                            ->title($user ? 'Đã mở khoá tài khoản' : 'Không có tài khoản nào dùng email này')
                            ->{$user ? 'success' : 'warning'}()
                            ->send();
                    }),
                Action::make('dismiss')->label('Bỏ qua')->icon(Heroicon::OutlinedXMark)->color('gray')
                    ->visible(fn (UnlockRequest $record) => $record->handled_at === null)
                    ->action(fn (UnlockRequest $record) => $record->update(['handled_at' => now()])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUnlockRequests::route('/')];
    }
}

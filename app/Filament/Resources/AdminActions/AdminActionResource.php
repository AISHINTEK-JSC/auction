<?php

namespace App\Filament\Resources\AdminActions;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\AdminActions\Pages\ManageAdminActions;
use App\Models\AdminAction;
use App\Support\AdminLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The audit log: read-only, newest first. Lines are written by App\Support\AdminLog.
 */
class AdminActionResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = AdminAction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'thao tác';

    protected static ?string $pluralModelLabel = 'Nhật ký thao tác';

    protected static ?string $navigationLabel = 'Nhật ký thao tác';

    protected static ?string $slug = 'audit-log';

    protected static ?int $navigationSort = 9;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
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
                TextColumn::make('created_at')->label('Thời gian')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('user.name')->label('Người thực hiện')->placeholder('(đã xoá)')->searchable(),
                TextColumn::make('action')->label('Thao tác')->badge()
                    ->formatStateUsing(fn (string $state) => AdminLog::label($state))
                    ->color(fn (string $state) => match (true) {
                        str_ends_with($state, '.deleted'), in_array($state, ['listing.hidden', 'listing.rejected', 'user.blocked', 'listing.auction_cancelled'], true) => 'danger',
                        str_starts_with($state, 'settings.'), $state === 'user.role_changed' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('summary')->label('Chi tiết')->wrap()->searchable(),
            ])
            ->filters([
                SelectFilter::make('action')->label('Thao tác')->options(AdminLog::LABELS),
                SelectFilter::make('user_id')->label('Người thực hiện')->relationship('user', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAdminActions::route('/')];
    }
}

<?php

namespace App\Filament\Resources\BlockedEmailDomains;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\BlockedEmailDomains\Pages\ManageBlockedEmailDomains;
use App\Models\BlockedEmailDomain;
use App\Support\AdminLog;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Blocks registration, see App\Actions\Fortify\CreateNewUser.
 */
class BlockedEmailDomainResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = BlockedEmailDomain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAtSymbol;

    protected static ?string $modelLabel = 'tên miền email bị chặn';

    protected static ?string $pluralModelLabel = 'Tên miền email bị chặn';

    // Set explicitly: Filament would title-case the plural label in the menu.
    protected static ?string $navigationLabel = 'Tên miền email bị chặn';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('domain')->label('Tên miền')->required()->maxLength(255)->unique(ignoreRecord: true)->helperText('Ví dụ: mailinator.com')->dehydrateStateUsing(fn (string $state) => strtolower(trim($state))),
            TextInput::make('description')->label('Ghi chú')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('domain')->label('Tên miền')->searchable(),
                TextColumn::make('description')->label('Ghi chú')->placeholder('—'),
                TextColumn::make('created_at')->label('Thêm lúc')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([
                EditAction::make()->after(fn (BlockedEmailDomain $record) => AdminLog::record('blocked_domain.updated', $record, 'Sửa tên miền email bị chặn "'.$record->domain.'"')),
                DeleteAction::make()->before(fn (BlockedEmailDomain $record) => AdminLog::record('blocked_domain.deleted', $record, 'Xoá tên miền email bị chặn "'.$record->domain.'"')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBlockedEmailDomains::route('/')];
    }
}

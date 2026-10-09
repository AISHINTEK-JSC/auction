<?php

namespace App\Filament\Resources\BlockedIps;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\BlockedIps\Pages\ManageBlockedIps;
use App\Models\BlockedIp;
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
class BlockedIpResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = BlockedIp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $modelLabel = 'IP bị chặn';

    protected static ?string $pluralModelLabel = 'IP bị chặn';

    // Set explicitly: Filament would title-case the plural label in the menu.
    protected static ?string $navigationLabel = 'IP bị chặn';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('address')->label('Địa chỉ IP')->required()->maxLength(45)->unique(ignoreRecord: true)->ip(),
            TextInput::make('description')->label('Ghi chú')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('address')->label('Địa chỉ IP')->searchable(),
                TextColumn::make('description')->label('Ghi chú')->placeholder('—'),
                TextColumn::make('created_at')->label('Thêm lúc')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBlockedIps::route('/')];
    }
}

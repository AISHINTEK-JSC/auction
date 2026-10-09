<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use App\Support\AdminLog;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'danh mục';

    protected static ?string $pluralModelLabel = 'Danh mục';

    // Set explicitly: Filament would title-case the plural label in the menu.
    protected static ?string $navigationLabel = 'Danh mục';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Tên')->required()->maxLength(255),
            TextInput::make('slug')->label('Slug')->required()->alphaDash()->maxLength(255)->unique(ignoreRecord: true)
                ->helperText('Dùng trong đường dẫn /c/{slug}.'),
            // The menu is two levels deep, so only top-level categories can be parents.
            Select::make('parent_id')->label('Danh mục cha')
                ->relationship(
                    'parent',
                    'name',
                    fn (Builder $query, ?Category $record) => $query
                        ->whereNull('parent_id')
                        ->when($record, fn (Builder $q) => $q->whereKeyNot($record->id)),
                )
                ->placeholder('Không có (danh mục gốc)'),
            TextInput::make('position')->label('Thứ tự')->integer()->default(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->label('Tên')->searchable(),
                TextColumn::make('slug'),
                TextColumn::make('parent.name')->label('Danh mục cha')->placeholder('—'),
                TextColumn::make('listings_count')->label('Tin đăng')->counts('listings'),
                TextColumn::make('position')->label('Thứ tự')->sortable(),
            ])
            ->recordActions([
                EditAction::make()->after(fn (Category $record) => AdminLog::record('category.updated', $record, 'Sửa danh mục "'.$record->name.'"')),
                DeleteAction::make()->before(fn (Category $record) => AdminLog::record('category.deleted', $record, 'Xoá danh mục "'.$record->name.'"')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}

<?php

namespace App\Filament\Resources\Comments;

use App\Filament\Resources\Comments\Pages\ManageComments;
use App\Models\Comment;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $modelLabel = 'bình luận';

    protected static ?string $pluralModelLabel = 'Bình luận';

    protected static ?int $navigationSort = 4;

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
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')->label('Người viết')->searchable(),
                TextColumn::make('listing.title')->label('Tin đăng')->limit(40)
                    ->url(fn (Comment $record) => route('listings.show', $record->listing_id), shouldOpenInNewTab: true),
                TextColumn::make('body')->label('Nội dung')->wrap()->limit(200)->searchable(),
                TextColumn::make('created_at')->label('Lúc')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageComments::route('/')];
    }
}

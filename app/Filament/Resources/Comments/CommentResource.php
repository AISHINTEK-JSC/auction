<?php

namespace App\Filament\Resources\Comments;

use App\Filament\Resources\Comments\Pages\ManageComments;
use App\Models\Comment;
use App\Support\AdminLog;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $modelLabel = 'bình luận';

    protected static ?string $pluralModelLabel = 'Bình luận';

    // Set explicitly: Filament would title-case the plural label in the menu.
    protected static ?string $navigationLabel = 'Bình luận';

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
                // Logged before the delete, while the comment can still be read.
                DeleteAction::make()->before(fn (Comment $record) => AdminLog::record('comment.deleted', $record, sprintf(
                    'Xoá bình luận của %s ở tin #%d: "%s"',
                    $record->user->name,
                    $record->listing_id,
                    Str::limit($record->body, 120),
                ))),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('deleteSelected')->label('Xoá các bình luận đã chọn')->icon(Heroicon::OutlinedTrash)->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int, Comment> $records */
                            AdminLog::record('comment.bulk_deleted', null, sprintf(
                                'Xoá %d bình luận: %s',
                                $records->count(),
                                $records->map(fn (Comment $comment) => '"'.Str::limit($comment->body, 40).'"')->join(', '),
                            ));

                            Comment::query()->whereKey($records->modelKeys())->delete();

                            Notification::make()->title('Đã xoá '.$records->count().' bình luận')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageComments::route('/')];
    }
}

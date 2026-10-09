<?php

namespace App\Filament\Resources\Giveaways;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\Giveaways\Pages\ManageGiveaways;
use App\Models\Giveaway;
use App\Services\GiveawayService;
use App\Support\AdminLog;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Prizes given away to members. Members sign up once each with their permanent
 * member number; "Quay thưởng" picks the winners at random.
 */
class GiveawayResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Giveaway::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $modelLabel = 'chương trình tặng quà';

    protected static ?string $pluralModelLabel = 'Tặng quà';

    // Set explicitly: Filament would title-case the plural label in the menu.
    protected static ?string $navigationLabel = 'Tặng quà';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Tên chương trình')->required()->maxLength(255),
            TextInput::make('prize')->label('Quà tặng')->required()->maxLength(255),
            Textarea::make('description')->label('Mô tả, thể lệ')->rows(4)->maxLength(5000),
            FileUpload::make('image_path')->label('Hình ảnh')->image()->disk('public')->directory('giveaways')->maxSize(5120),
            TextInput::make('winner_count')->label('Số người trúng')->integer()->minValue(1)->maxValue(1000)->default(1)->required(),
            DateTimePicker::make('ends_at')->label('Hạn đăng ký')->seconds(false)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label('Chương trình')->searchable()
                    ->url(fn (Giveaway $record) => route('giveaways.show', $record), shouldOpenInNewTab: true),
                TextColumn::make('prize')->label('Quà tặng')->limit(40),
                TextColumn::make('entries_count')->label('Đã đăng ký')->counts('entries')->sortable(),
                TextColumn::make('winner_count')->label('Số người trúng'),
                TextColumn::make('ends_at')->label('Hạn đăng ký')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('drawn_at')->label('Quay thưởng lúc')->dateTime('d/m/Y H:i')->placeholder('Chưa quay'),
            ])
            ->recordActions([
                Action::make('entries')->label('Người tham gia')->icon(Heroicon::OutlinedUsers)->color('gray')
                    ->modalHeading(fn (Giveaway $record) => 'Người tham gia: '.$record->title)
                    ->modalContent(fn (Giveaway $record) => view('filament.giveaway-entries', [
                        'entries' => $record->entries()->with('user')->orderByDesc('is_winner')->oldest()->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng'),
                Action::make('draw')->label('Quay thưởng')->icon(Heroicon::OutlinedSparkles)->color('success')
                    ->visible(fn (Giveaway $record) => ! $record->isDrawn())
                    ->requiresConfirmation()
                    ->modalDescription(fn (Giveaway $record) => sprintf(
                        'Chọn ngẫu nhiên %d người trong %d người đã đăng ký. Chỉ quay được một lần và việc đăng ký sẽ đóng lại.',
                        $record->winner_count,
                        $record->entries()->count(),
                    ))
                    ->action(function (Giveaway $record): void {
                        try {
                            $winners = app(GiveawayService::class)->draw($record);
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        AdminLog::record('giveaway.drawn', $record, sprintf(
                            'Quay thưởng "%s": %s',
                            $record->title,
                            $winners->map(fn ($entry) => $entry->user->name.' ('.$entry->user->member_code.')')->join(', '),
                        ));

                        Notification::make()
                            ->title('Đã quay thưởng')
                            ->body('Người trúng: '.$winners->map(fn ($entry) => $entry->user->name.' ('.$entry->user->member_code.')')->join(', '))
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                ActionGroup::make([
                    EditAction::make()->visible(fn (Giveaway $record) => ! $record->isDrawn())
                        ->after(fn (Giveaway $record) => AdminLog::record('giveaway.updated', $record, 'Sửa chương trình quà tặng "'.$record->title.'"')),
                    DeleteAction::make()
                        ->before(fn (Giveaway $record) => AdminLog::record('giveaway.deleted', $record, 'Xoá chương trình quà tặng "'.$record->title.'"')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGiveaways::route('/')];
    }
}

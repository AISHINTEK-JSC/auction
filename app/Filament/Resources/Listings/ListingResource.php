<?php

namespace App\Filament\Resources\Listings;

use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
use App\Enums\ModerationStatus;
use App\Filament\Resources\Listings\Pages\ManageListings;
use App\Models\Listing;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use App\Services\ListingModeration;
use App\Services\ListingSyncService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ListingResource extends Resource
{
    protected static ?string $model = Listing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $modelLabel = 'tin đấu giá';

    protected static ?string $pluralModelLabel = 'Tin đấu giá';

    // Set explicitly: Filament would title-case the plural label in the menu.
    protected static ?string $navigationLabel = 'Tin đấu giá';

    protected static ?int $navigationSort = 2;

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
                TextColumn::make('title')->label('Tiêu đề')->searchable()->limit(50)
                    ->url(fn (Listing $record) => $record->auction_id ? route('listings.show', $record) : null, shouldOpenInNewTab: true),
                TextColumn::make('user.name')->label('Người bán')->searchable(),
                TextColumn::make('moderation_status')->label('Kiểm duyệt')->badge()
                    ->formatStateUsing(fn (ModerationStatus $state) => $state->label())
                    ->color(fn (ModerationStatus $state) => match ($state) {
                        ModerationStatus::Pending => 'warning',
                        ModerationStatus::Approved => 'success',
                        ModerationStatus::Rejected, ModerationStatus::Hidden => 'danger',
                    })
                    ->description(fn (Listing $record) => $record->moderation_note),
                TextColumn::make('reports_count')->label('Báo cáo')->counts('reports')->sortable(),
                TextColumn::make('auction_id')->label('Auction ID')->placeholder('—'),
                TextColumn::make('auction_type')->label('Loại')->formatStateUsing(fn (AuctionType $state) => $state->label()),
                TextColumn::make('auction_status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (AuctionStatus $state) => $state->label())
                    ->color(fn (AuctionStatus $state) => match ($state) {
                        AuctionStatus::Running => 'success',
                        AuctionStatus::Upcoming => 'info',
                        AuctionStatus::Completed => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('current_price')->label('Giá hiện tại')->numeric(thousandsSeparator: '.')->sortable(),
                TextColumn::make('bid_count')->label('Lượt đặt')->sortable(),
                TextColumn::make('ends_at')->label('Kết thúc')->dateTime('d/m/Y H:i')->sortable()->placeholder('—'),
                TextColumn::make('synced_at')->label('Đồng bộ lúc')->since()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('moderation_status')->label('Kiểm duyệt')
                    ->options(collect(ModerationStatus::cases())->mapWithKeys(fn (ModerationStatus $status) => [$status->value => $status->label()])),
                SelectFilter::make('auction_status')->label('Trạng thái')
                    ->options(collect(AuctionStatus::cases())->mapWithKeys(fn (AuctionStatus $status) => [$status->value => $status->label()])),
            ])
            ->recordActions([
                Action::make('approve')->label('Duyệt')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (Listing $record) => $record->moderation_status !== ModerationStatus::Approved)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Listing $record) => $record->auction_id === null
                        ? 'Duyệt sẽ mở phiên đấu giá của tin này trong hệ thống đấu giá.'
                        : 'Tin sẽ hiển thị lại trên sàn.')
                    ->action(fn (Listing $record) => self::callEngine(
                        fn () => app(ListingModeration::class)->approve($record),
                        'Đã duyệt tin',
                    )),
                Action::make('reject')->label('Từ chối')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (Listing $record) => $record->moderation_status === ModerationStatus::Pending)
                    ->schema([Textarea::make('note')->label('Lý do (gửi cho người bán)')->required()->maxLength(255)])
                    ->action(function (Listing $record, array $data): void {
                        app(ListingModeration::class)->reject($record, $data['note']);

                        Notification::make()->title('Đã từ chối tin')->success()->send();
                    }),
                Action::make('hide')->label('Ẩn tin')->icon(Heroicon::OutlinedEyeSlash)->color('danger')
                    ->visible(fn (Listing $record) => $record->moderation_status === ModerationStatus::Approved)
                    ->schema([Textarea::make('note')->label('Lý do (gửi cho người bán)')->required()->maxLength(255)])
                    ->modalDescription('Tin sẽ không còn hiển thị. Phiên đấu giá vẫn chạy trong hệ thống đấu giá; dùng "Huỷ phiên" nếu cần dừng.')
                    ->action(function (Listing $record, array $data): void {
                        app(ListingModeration::class)->hide($record, $data['note']);
                        $record->reports()->whereNull('handled_at')->update(['handled_at' => now(), 'handled_by' => auth()->id()]);

                        Notification::make()->title('Đã ẩn tin')->success()->send();
                    }),
                Action::make('sync')->label('Đồng bộ')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->visible(fn (Listing $record) => $record->auction_id !== null)
                    ->action(fn (Listing $record) => self::callEngine(
                        fn () => app(ListingSyncService::class)->refresh($record),
                        'Đã đồng bộ với hệ thống đấu giá',
                    )),
                Action::make('cancel')->label('Huỷ phiên')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (Listing $record) => $record->auction_id !== null && $record->auction_status->isOpen())
                    ->requiresConfirmation()
                    ->action(fn (Listing $record) => self::callEngine(function () use ($record): void {
                        app(AuctionEngineClient::class)->cancelAuction((int) $record->auction_id);
                        app(ListingSyncService::class)->refresh($record);
                    }, 'Đã huỷ phiên đấu giá')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageListings::route('/')];
    }

    private static function callEngine(callable $callback, string $success): void
    {
        try {
            $callback();

            Notification::make()->title($success)->success()->send();
        } catch (EngineException|DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}

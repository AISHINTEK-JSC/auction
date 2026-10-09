<?php

namespace App\Filament\Resources\Listings;

use App\Enums\AuctionStatus;
use App\Enums\AuctionType;
use App\Filament\Resources\Listings\Pages\ManageListings;
use App\Models\Listing;
use App\Services\AuctionEngine\AuctionEngineClient;
use App\Services\AuctionEngine\EngineException;
use App\Services\ListingSyncService;
use BackedEnum;
use Filament\Actions\Action;
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
                SelectFilter::make('auction_status')->label('Trạng thái')
                    ->options(collect(AuctionStatus::cases())->mapWithKeys(fn (AuctionStatus $status) => [$status->value => $status->label()])),
            ])
            ->recordActions([
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
        } catch (EngineException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}

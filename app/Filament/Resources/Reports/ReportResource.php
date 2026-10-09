<?php

namespace App\Filament\Resources\Reports;

use App\Enums\ModerationStatus;
use App\Enums\ReportReason;
use App\Filament\Resources\Reports\Pages\ManageReports;
use App\Models\ListingReport;
use App\Services\ListingModeration;
use App\Support\AdminLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReportResource extends Resource
{
    protected static ?string $model = ListingReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $modelLabel = 'báo cáo vi phạm';

    protected static ?string $pluralModelLabel = 'Báo cáo vi phạm';

    // Set explicitly: Filament would title-case the plural label in the menu.
    protected static ?string $navigationLabel = 'Báo cáo vi phạm';

    protected static ?string $slug = 'reports';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = ListingReport::query()->whereNull('handled_at')->count();

        return $open > 0 ? (string) $open : null;
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
                TextColumn::make('listing.title')->label('Tin đăng')->limit(40)->searchable()
                    ->url(fn (ListingReport $record) => $record->listing->auction_id ? route('listings.show', $record->listing_id) : null, shouldOpenInNewTab: true)
                    ->description(fn (ListingReport $record) => $record->listing->moderation_status->label()),
                TextColumn::make('reason')->label('Lý do')->badge()->formatStateUsing(fn (ReportReason $state) => $state->label()),
                TextColumn::make('note')->label('Chi tiết')->wrap()->placeholder('—'),
                TextColumn::make('user.name')->label('Người báo cáo')->searchable(),
                TextColumn::make('created_at')->label('Gửi lúc')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('handled_at')->label('Xử lý lúc')->dateTime('d/m/Y H:i')->placeholder('Chưa xử lý'),
            ])
            ->filters([
                TernaryFilter::make('handled_at')->label('Đã xử lý')->nullable(),
            ])
            ->recordActions([
                Action::make('hideListing')->label('Ẩn tin')->icon(Heroicon::OutlinedEyeSlash)->color('danger')
                    ->visible(fn (ListingReport $record) => $record->handled_at === null && $record->listing->moderation_status === ModerationStatus::Approved)
                    ->schema([Textarea::make('note')->label('Lý do (gửi cho người bán)')->required()->maxLength(255)])
                    ->action(function (ListingReport $record, array $data): void {
                        app(ListingModeration::class)->hide($record->listing, $data['note']);

                        // Every open report on this listing is answered by hiding it.
                        ListingReport::query()
                            ->where('listing_id', $record->listing_id)
                            ->whereNull('handled_at')
                            ->update(['handled_at' => now(), 'handled_by' => auth()->id()]);

                        Notification::make()->title('Đã ẩn tin và đóng các báo cáo')->success()->send();
                    }),
                Action::make('dismiss')->label('Bỏ qua')->icon(Heroicon::OutlinedXMark)->color('gray')
                    ->visible(fn (ListingReport $record) => $record->handled_at === null)
                    ->action(function (ListingReport $record): void {
                        $record->update(['handled_at' => now(), 'handled_by' => auth()->id()]);

                        AdminLog::record('report.dismissed', $record->listing, sprintf(
                            'Bỏ qua báo cáo "%s" về tin #%d "%s"',
                            $record->reason->label(),
                            $record->listing_id,
                            $record->listing->title,
                        ));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReports::route('/')];
    }
}

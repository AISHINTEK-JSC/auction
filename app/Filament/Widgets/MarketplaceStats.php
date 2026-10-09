<?php

namespace App\Filament\Widgets;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MarketplaceStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * Figures come from the listing snapshots, which follow the auction engine
     * with up to a minute of delay.
     *
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $auctions = Listing::query()->whereNotNull('auction_id');
        $completed = (clone $auctions)->where('auction_status', AuctionStatus::Completed);
        $monthAgo = now()->subDays(30);

        return [
            Stat::make('Phiên đấu giá', self::number((clone $auctions)->count()))
                ->description(sprintf(
                    '%s đang diễn ra · %s sắp diễn ra',
                    self::number((clone $auctions)->where('auction_status', AuctionStatus::Running)->count()),
                    self::number((clone $auctions)->where('auction_status', AuctionStatus::Upcoming)->count()),
                )),
            Stat::make('Phiên thành công', self::number((clone $completed)->count()))
                ->description(self::number((clone $completed)->where('ends_at', '>=', $monthAgo)->count()).' trong 30 ngày qua'),
            Stat::make('Doanh số', self::number((int) (clone $completed)->sum('winning_price')).' đ')
                ->description(self::number((int) (clone $completed)->where('ends_at', '>=', $monthAgo)->sum('winning_price')).' đ trong 30 ngày qua'),
            Stat::make('Người dùng mới (7 ngày)', self::number(User::query()->where('created_at', '>=', now()->subDays(7))->count()))
                ->description(sprintf(
                    '%s trong 30 ngày · %s tất cả',
                    self::number(User::query()->where('created_at', '>=', $monthAgo)->count()),
                    self::number(User::query()->count()),
                )),
            Stat::make('Tin chờ duyệt', self::number(Listing::query()->where('moderation_status', ModerationStatus::Pending)->count()))
                ->color('warning'),
            Stat::make('Báo cáo chưa xử lý', self::number(ListingReport::query()->whereNull('handled_at')->count()))
                ->color('danger'),
        ];
    }

    private static function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}

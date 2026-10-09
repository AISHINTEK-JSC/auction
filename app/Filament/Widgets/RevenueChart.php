<?php

namespace App\Filament\Widgets;

use App\Enums\AuctionStatus;
use App\Models\Listing;
use App\Support\DailySeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Value of auctions won per day (the sum of closing prices). Fixed-price sales
 * are settled between the two parties, so the marketplace has no price for them.
 */
class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Doanh số đấu giá theo ngày';

    protected ?string $description = 'Tổng giá chốt của các phiên có người thắng.';

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '30d';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return ['7d' => '7 ngày', '30d' => '30 ngày', '90d' => '90 ngày'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $days = DailySeries::days((int) $this->filter);

        return [
            'labels' => DailySeries::labels($days),
            'datasets' => [
                [
                    'label' => 'Doanh số (đ)',
                    'data' => DailySeries::sum(
                        Listing::query()->where('auction_status', AuctionStatus::Completed)->whereNotNull('winning_price'),
                        'ends_at',
                        'winning_price',
                        $days,
                    ),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        // Thousands separators on the axis and in the tooltip.
        return RawJs::make(<<<'JS'
            {
                scales: {
                    y: { ticks: { callback: (value) => new Intl.NumberFormat('vi-VN').format(value) } },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => new Intl.NumberFormat('vi-VN').format(context.parsed.y) + ' đ',
                        },
                    },
                },
            }
        JS);
    }
}

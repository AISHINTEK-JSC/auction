<?php

namespace App\Filament\Widgets;

use App\Enums\AuctionStatus;
use App\Enums\ModerationStatus;
use App\Models\Listing;
use App\Models\User;
use App\Support\DailySeries;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * New listings, completed sales and new members per day.
 */
class ActivityChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Hoạt động theo ngày';

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '30d';

    protected function getType(): string
    {
        return 'line';
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
                    'label' => 'Tin mới',
                    'data' => DailySeries::count(
                        Listing::query()
                            ->where('moderation_status', '!=', ModerationStatus::Draft)
                            ->where(fn (Builder $live) => $live->where('sale_type', 'fixed')->orWhereNotNull('auction_id')),
                        'created_at',
                        $days,
                    ),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'Giao dịch thành công',
                    'data' => DailySeries::count(Listing::query()->where('auction_status', AuctionStatus::Completed), 'ends_at', $days),
                    'borderColor' => '#22c55e',
                    'backgroundColor' => '#22c55e',
                ],
                [
                    'label' => 'Thành viên mới',
                    'data' => DailySeries::count(User::query(), 'created_at', $days),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => '#3b82f6',
                ],
            ],
        ];
    }
}

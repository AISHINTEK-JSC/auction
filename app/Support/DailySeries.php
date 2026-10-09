<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-day figures for the dashboard charts. Rows are grouped in PHP rather
 * than with a SQL date function, so it behaves the same on MySQL and SQLite
 * and always in the application's timezone.
 */
class DailySeries
{
    /**
     * The last N days, oldest first, as "Y-m-d".
     *
     * @return list<string>
     */
    public static function days(int $count): array
    {
        $count = max(1, min($count, 365));
        $today = CarbonImmutable::today();

        return array_map(
            fn (int $offset) => $today->subDays($count - 1 - $offset)->format('Y-m-d'),
            range(0, $count - 1),
        );
    }

    /**
     * @param  list<string>  $days
     * @return list<string>
     */
    public static function labels(array $days): array
    {
        return array_map(fn (string $day) => CarbonImmutable::parse($day)->format('d/m'), $days);
    }

    /**
     * How many rows fall on each day.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $days
     * @return list<int>
     */
    public static function count(Builder $query, string $dateColumn, array $days): array
    {
        return self::tally($query, $dateColumn, null, $days);
    }

    /**
     * The sum of a column over the rows of each day.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $days
     * @return list<int>
     */
    public static function sum(Builder $query, string $dateColumn, string $valueColumn, array $days): array
    {
        return self::tally($query, $dateColumn, $valueColumn, $days);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $days
     * @return list<int>
     */
    private static function tally(Builder $query, string $dateColumn, ?string $valueColumn, array $days): array
    {
        $totals = array_fill_keys($days, 0);

        $query
            ->where($dateColumn, '>=', CarbonImmutable::parse($days[0])->startOfDay())
            ->toBase()
            ->get(array_filter([$dateColumn, $valueColumn]))
            ->each(function (object $row) use (&$totals, $dateColumn, $valueColumn): void {
                $day = CarbonImmutable::parse($row->{$dateColumn})->format('Y-m-d');

                if (isset($totals[$day])) {
                    $totals[$day] += $valueColumn === null ? 1 : (int) $row->{$valueColumn};
                }
            });

        return array_values($totals);
    }
}

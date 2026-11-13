<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\DateRangeDto;
use App\Report\PeriodFilter;
use App\Report\PeriodRangeFactory;
use App\Report\PeriodSelectionDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the date ranges of the selected period and the comparison period, the links to the
 * previous and the next period, and the 12 months of the trend. Today is Friday 18 September 2026,
 * as in the prototype, unless a test says otherwise.
 */
final class PeriodRangeFactoryTest extends TestCase
{
    private const string TODAY = '2026-09-18';

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('periods')]
    public function testSelectedPeriod(array $query, string $range, bool $partial, string $today = self::TODAY): void
    {
        $selection = self::select($query, $today);

        self::assertSame($range, self::format($selection->selected->range));
        self::assertSame($partial, $selection->selected->partial);
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: string, 2: bool, 3?: string}>
     */
    public static function periods(): iterable
    {
        yield 'day' => [['p' => 'day', 'd' => '2026-09-15'], '2026-09-15/2026-09-15', false];
        // A day is never partial, also today.
        yield 'today' => [['p' => 'day'], '2026-09-18/2026-09-18', false];
        yield 'week from Monday to Sunday' => [['p' => 'week', 'd' => '2026-09-09'], '2026-09-07/2026-09-13', false];
        yield 'current week ends today' => [['p' => 'week', 'd' => '2026-09-16'], '2026-09-14/2026-09-18', true];
        yield 'week 53 across a year' => [['p' => 'week', 'd' => '2027-01-02'], '2026-12-28/2027-01-03', false, '2027-06-01'];
        yield 'month' => [['d' => '2026-08-20'], '2026-08-01/2026-08-31', false];
        yield 'current month ends today' => [[], '2026-09-01/2026-09-18', true];
        yield 'leap day month' => [['d' => '2028-02-10'], '2028-02-01/2028-02-29', false, '2028-06-01'];
        yield 'year' => [['p' => 'year', 'd' => '2025-05-05'], '2025-01-01/2025-12-31', false];
        yield 'current year ends today' => [['p' => 'year'], '2026-01-01/2026-09-18', true];
    }

    /**
     * The comparison of a period that has not ended has the same number of days.
     *
     * @param array<string, string> $query
     */
    #[DataProvider('comparisons')]
    public function testComparisonPeriod(array $query, string $period, string $range, string $today = self::TODAY): void
    {
        $selection = self::select($query, $today);

        self::assertSame($period, self::format($selection->comparison->range));
        self::assertSame($range, self::format($selection->comparisonRange));
        self::assertSame($period !== $range, $selection->isComparisonCut());
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: string, 2: string, 3?: string}>
     */
    public static function comparisons(): iterable
    {
        yield 'previous day' => [['p' => 'day', 'd' => '2026-09-15'], '2026-09-14/2026-09-14', '2026-09-14/2026-09-14'];
        yield 'same day last year' => [['p' => 'day', 'd' => '2026-09-15', 'c' => 'last_year'], '2025-09-15/2025-09-15', '2025-09-15/2025-09-15'];
        yield 'leap day last year' => [['p' => 'day', 'd' => '2028-02-29', 'c' => 'last_year'], '2027-02-28/2027-02-28', '2027-02-28/2027-02-28', '2028-06-01'];
        yield 'previous week' => [['p' => 'week', 'd' => '2026-09-09'], '2026-08-31/2026-09-06', '2026-08-31/2026-09-06'];
        yield 'previous week, cut' => [['p' => 'week', 'd' => '2026-09-16'], '2026-09-07/2026-09-13', '2026-09-07/2026-09-11'];
        yield 'week 1 after week 53' => [['p' => 'week', 'd' => '2027-01-05'], '2026-12-28/2027-01-03', '2026-12-28/2027-01-03', '2027-06-01'];
        // The same week last year has the same ISO week number, from each day of the week.
        yield 'same week last year from a Monday' => [['p' => 'week', 'd' => '2026-09-07', 'c' => 'last_year'], '2025-09-08/2025-09-14', '2025-09-08/2025-09-14'];
        yield 'same week last year from a Sunday' => [['p' => 'week', 'd' => '2026-09-13', 'c' => 'last_year'], '2025-09-08/2025-09-14', '2025-09-08/2025-09-14'];
        yield 'same week last year, cut' => [['p' => 'week', 'c' => 'last_year'], '2025-09-15/2025-09-21', '2025-09-15/2025-09-19'];
        // Week 1 of 2026 starts in 2025. Its ISO year is 2026, so last year is week 1 of 2025.
        yield 'week 1 last year' => [['p' => 'week', 'd' => '2025-12-30', 'c' => 'last_year'], '2024-12-30/2025-01-05', '2024-12-30/2025-01-05'];
        yield 'week 1 last year, after week 53' => [['p' => 'week', 'd' => '2027-01-05', 'c' => 'last_year'], '2025-12-29/2026-01-04', '2025-12-29/2026-01-04', '2027-06-01'];
        // 2025 has 52 weeks. Week 53 of 2026 is compared with week 52, as 29 February with 28 February.
        yield 'week 53 last year is week 52' => [['p' => 'week', 'd' => '2027-01-02', 'c' => 'last_year'], '2025-12-22/2025-12-28', '2025-12-22/2025-12-28', '2027-06-01'];
        yield 'previous month' => [['d' => '2026-08-20'], '2026-07-01/2026-07-31', '2026-07-01/2026-07-31'];
        yield 'previous month, cut' => [[], '2026-08-01/2026-08-31', '2026-08-01/2026-08-18'];
        yield 'previous month of March' => [['d' => '2026-03-31'], '2026-02-01/2026-02-28', '2026-02-01/2026-02-28'];
        yield 'same month last year, cut' => [['c' => 'last_year'], '2025-09-01/2025-09-30', '2025-09-01/2025-09-18'];
        yield 'previous year' => [['p' => 'year', 'd' => '2025-05-05'], '2024-01-01/2024-12-31', '2024-01-01/2024-12-31'];
        yield 'previous year, cut' => [['p' => 'year'], '2025-01-01/2025-12-31', '2025-01-01/2025-09-18'];
        // A cut February has fewer days than the selected days. It is not cut to a date in March.
        yield 'cut longer than the comparison' => [[], '2026-02-01/2026-02-28', '2026-02-01/2026-02-28', '2026-03-30'];
    }

    /**
     * The links go to the first date of the previous and the next period. The next period has no
     * link when it starts after today.
     *
     * @param array<string, string> $query
     */
    #[DataProvider('steps')]
    public function testPreviousAndNextPeriod(array $query, string $previous, ?string $next): void
    {
        $selection = self::select($query);

        self::assertSame($previous, $selection->previousAnchor->format('Y-m-d'));
        self::assertSame($next, $selection->nextAnchor?->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{array<string, string>, string, ?string}>
     */
    public static function steps(): iterable
    {
        yield 'day' => [['p' => 'day', 'd' => '2026-09-15'], '2026-09-14', '2026-09-16'];
        yield 'today' => [['p' => 'day'], '2026-09-17', null];
        yield 'week' => [['p' => 'week', 'd' => '2026-09-09'], '2026-08-31', '2026-09-14'];
        // The next week of a Saturday is not after today. Its link is the Monday.
        yield 'week from a Saturday' => [['p' => 'week', 'd' => '2026-09-12'], '2026-08-31', '2026-09-14'];
        yield 'current week' => [['p' => 'week', 'd' => '2026-09-16'], '2026-09-07', null];
        yield 'month' => [['d' => '2026-08-20'], '2026-07-01', '2026-09-01'];
        yield 'month from the 31st' => [['d' => '2026-07-31'], '2026-06-01', '2026-08-01'];
        yield 'current month' => [[], '2026-08-01', null];
        yield 'year' => [['p' => 'year', 'd' => '2025-05-05'], '2024-01-01', '2026-01-01'];
        yield 'current year' => [['p' => 'year'], '2025-01-01', null];
    }

    /**
     * The trend ends at the month of the last date of the period, up to today. The date in the
     * period has no effect.
     *
     * @param array<string, string> $query
     */
    #[DataProvider('trends')]
    public function testTrendHasTheTwelveMonthsUpToThePeriod(array $query, string $first, string $last): void
    {
        $months = self::select($query)->months;

        self::assertCount(12, $months);
        self::assertSame($first, $months[0]->query());
        self::assertSame($last, $months[11]->query());
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function trends(): iterable
    {
        yield 'day' => [['p' => 'day', 'd' => '2026-03-04'], '2025-04', '2026-03'];
        yield 'week in one month' => [['p' => 'week', 'd' => '2026-03-04'], '2025-04', '2026-03'];
        yield 'week across two months' => [['p' => 'week', 'd' => '2026-03-31'], '2025-05', '2026-04'];
        yield 'month' => [['d' => '2026-08-20'], '2025-09', '2026-08'];
        yield 'year from May' => [['p' => 'year', 'd' => '2025-05-05'], '2025-01', '2025-12'];
        yield 'year from 1 January' => [['p' => 'year', 'd' => '2025-01-01'], '2025-01', '2025-12'];
        yield 'current year' => [['p' => 'year'], '2025-10', '2026-09'];
    }

    public function testSelectionKeepsTheFilter(): void
    {
        $filter = PeriodFilter::fromQuery('week', null, 'last_year', '3', new \DateTimeImmutable(self::TODAY));

        self::assertSame($filter, new PeriodRangeFactory()->create($filter)->filter);
    }

    /**
     * @param array<string, string> $query
     */
    private static function select(array $query, string $today = self::TODAY): PeriodSelectionDto
    {
        $filter = PeriodFilter::fromQuery($query['p'] ?? null, $query['d'] ?? null, $query['c'] ?? null, null, new \DateTimeImmutable($today));

        return new PeriodRangeFactory()->create($filter);
    }

    private static function format(DateRangeDto $range): string
    {
        return $range->firstDate->format('Y-m-d').'/'.$range->lastDate->format('Y-m-d');
    }
}

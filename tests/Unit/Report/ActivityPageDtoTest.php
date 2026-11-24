<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\ActivityPageDto;
use App\Report\CountTrendDto;
use App\Report\DayTotalsDto;
use App\Report\MonthDto;
use App\Report\ShareTrendDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks the figures of the tiles of the activity page: the counts and the share of the cancelled
 * trips, with the change from the comparison period and the history of the months.
 */
final class ActivityPageDtoTest extends TestCase
{
    public function testCountsHaveTheChangeAndTheHistory(): void
    {
        $page = self::page(self::totals(10, 6, 3, 1, 4), self::totals(8, 7, 1, 0, 5));

        self::assertEquals(new CountTrendDto(10, 2, [null, 8.0]), $page->trips());
        self::assertEquals(new CountTrendDto(6, -1, [null, 7.0]), $page->completed());
        self::assertEquals(new CountTrendDto(4, -1, [null, 5.0]), $page->runs());
    }

    /**
     * The share of the cancelled trips is of all trips. The change is in percentage points.
     */
    public function testCancelledShareIsOfAllTrips(): void
    {
        $page = self::page(self::totals(10, 6, 3, 1, 4), self::totals(8, 7, 2, 0, 5));

        self::assertEquals(new ShareTrendDto(30.0, 5.0, [null, 25.0]), $page->cancelled());
    }

    /**
     * A period with no trips has no data, so its values are null, not 0.
     */
    public function testPeriodWithNoTripsHasNoValues(): void
    {
        $page = self::page(DayTotalsDto::empty(), self::totals(8, 7, 1, 0, 5));

        self::assertEquals(new CountTrendDto(null, null, [null, 8.0]), $page->trips());
        self::assertEquals(new CountTrendDto(null, null, [null, 5.0]), $page->runs());
        self::assertEquals(new ShareTrendDto(null, null, [null, 12.5]), $page->cancelled());
    }

    /**
     * A comparison period with no data gives no change.
     */
    public function testNoComparisonGivesNoChange(): void
    {
        $page = self::page(self::totals(10, 6, 3, 1, 4), null);

        self::assertNull($page->trips()->change);
        self::assertNull($page->cancelled()->change);
    }

    /**
     * A comparison period that starts before the data has fewer days. A change of a count then
     * only shows the extra days. A share has no such fault, so it is still compared.
     */
    public function testPartialComparisonComparesOnlyTheShare(): void
    {
        $page = self::page(self::totals(10, 6, 3, 1, 4), self::totals(8, 7, 2, 0, 5), comparisonIsPartial: true);

        self::assertNull($page->trips()->change);
        self::assertNull($page->completed()->change);
        self::assertNull($page->runs()->change);
        self::assertSame(5.0, $page->cancelled()->change);
    }

    /**
     * The selected period is the last month of the history. The month before it has no data.
     */
    private static function page(DayTotalsDto $selected, ?DayTotalsDto $comparison, bool $comparisonIsPartial = false): ActivityPageDto
    {
        return new ActivityPageDto(
            [MonthDto::of(new \DateTimeImmutable('2025-12-01')), MonthDto::of(new \DateTimeImmutable('2026-01-01'))],
            [DayTotalsDto::empty(), $comparison ?? DayTotalsDto::empty()],
            $selected,
            $comparison,
            $comparisonIsPartial,
        );
    }

    private static function totals(int $trips, int $completed, int $cancelled, int $pending, int $runs): DayTotalsDto
    {
        $fields = ['trips', 'completedTrips', 'cancelledTrips', 'pendingTrips', 'notCancelledRuns'];

        return DayTotalsDto::fromRow(array_combine($fields, [$trips, $completed, $cancelled, $pending, $runs]), $fields);
    }
}

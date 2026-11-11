<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\CountTrendDto;
use App\Report\MonthDto;
use App\Report\OverviewPageDto;
use App\Report\SoloCountDto;
use App\Report\TripCountDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks the trips and the citizens of the overview: the month of the page, the change from the
 * month before it, and the history for the sparklines.
 */
final class OverviewPageDtoTest extends TestCase
{
    public function testMonthIsTheLastMonth(): void
    {
        $page = self::page([self::tripCount(1, 1), self::tripCount(2, 2), self::tripCount(3, 3)]);

        self::assertSame('2026-03', $page->month()->query());
        self::assertEquals(self::tripCount(3, 3), $page->current());
    }

    public function testTripsHaveTheChangeFromThePreviousMonth(): void
    {
        $page = self::page([self::tripCount(10, 4), self::tripCount(12, 5), self::tripCount(9, 6)]);

        self::assertTrend(9, -3, [10.0, 12.0, 9.0], $page->trips());
        self::assertTrend(6, 1, [4.0, 5.0, 6.0], $page->citizens());
    }

    /**
     * A month with no trips has no data, so its values are null, not 0.
     */
    public function testMonthWithNoTripsHasNoValue(): void
    {
        $page = self::page([self::tripCount(10, 4), self::tripCount(0, 0), self::tripCount(9, 6)]);

        self::assertTrend(9, null, [10.0, null, 9.0], $page->trips());
        self::assertTrend(6, null, [4.0, null, 6.0], $page->citizens());
    }

    public function testCurrentMonthWithNoTripsHasNoValue(): void
    {
        $page = self::page([self::tripCount(10, 4), self::tripCount(0, 0)]);

        self::assertTrend(null, null, [10.0, null], $page->trips());
    }

    public function testFirstMonthHasNoChange(): void
    {
        $page = self::page([self::tripCount(10, 4)]);

        self::assertTrend(10, null, [10.0], $page->trips());
    }

    /**
     * The share of the real solo runs, and the change in percentage points.
     */
    public function testRealSoloRunsHaveTheShareAndTheChange(): void
    {
        $page = self::page(
            [self::tripCount(10, 4), self::tripCount(12, 5), self::tripCount(9, 6)],
            [new SoloCountDto(4, 2), new SoloCountDto(0, 0), new SoloCountDto(8, 2)],
        );

        $solo = $page->realSolo();

        self::assertSame(25.0, $solo->current);
        // February has no runs, so there is no change.
        self::assertNull($solo->change);
        self::assertSame([50.0, null, 25.0], $solo->history);
        self::assertEquals(new SoloCountDto(8, 2), $page->currentSolo());
    }

    public function testRealSoloRunsChangeFromThePreviousMonth(): void
    {
        $page = self::page(
            [self::tripCount(10, 4), self::tripCount(12, 5)],
            [new SoloCountDto(4, 1), new SoloCountDto(4, 2)],
        );

        self::assertSame(25.0, $page->realSolo()->change);
    }

    /**
     * The months end with March 2026. The solo runs are 1 of 2 runs in each month by default.
     *
     * @param non-empty-list<TripCountDto>  $counts
     * @param ?non-empty-list<SoloCountDto> $solos
     */
    private static function page(array $counts, ?array $solos = null): OverviewPageDto
    {
        $months = MonthDto::fromQuery('2026-03', new \DateTimeImmutable('2026-11-11'))->trailing(\count($counts));

        return new OverviewPageDto($months, $counts, $solos ?? array_map(static fn (): SoloCountDto => new SoloCountDto(2, 1), $counts));
    }

    private static function tripCount(int $trips, int $citizens): TripCountDto
    {
        return new TripCountDto($trips, $trips, 0, 0, $citizens);
    }

    /**
     * Compares with assertSame, so that the history must have floats, as the sparklines need.
     *
     * @param list<float|null> $history
     */
    private static function assertTrend(?int $current, ?int $change, array $history, CountTrendDto $trend): void
    {
        self::assertSame($current, $trend->current);
        self::assertSame($change, $trend->change);
        self::assertSame($history, $trend->history);
    }
}

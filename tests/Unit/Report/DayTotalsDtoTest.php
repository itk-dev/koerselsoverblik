<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\DayTotalsDto;
use App\Report\DelayBandsDto;
use App\Report\PunctualityCountDto;
use App\Report\PunctualityFiguresDto;
use App\Report\SoloCountDto;
use App\Report\TripCountDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks the counters of one route and day, and the figures that the pages read from a sum of
 * them.
 */
final class DayTotalsDtoTest extends TestCase
{
    public function testEmptyHasOnlyZeros(): void
    {
        self::assertEquals(self::totals(0), DayTotalsDto::empty());
    }

    /**
     * MariaDB gives a sum as text, and no sum when no row matches. A key that is not a counter is
     * not read.
     */
    public function testFromRowReadsTextAndMissingCounters(): void
    {
        $totals = DayTotalsDto::fromRow(['trips' => '7', 'runs' => 4, 'soloRuns' => null, 'route' => '12', 'month' => '2026-01']);

        self::assertSame(7, $totals->trips);
        self::assertSame(4, $totals->runs);
        self::assertSame(0, $totals->soloRuns);
        self::assertSame(0, $totals->pickupOnTime);
    }

    public function testAddAddsEachCounter(): void
    {
        self::assertEquals(self::totals(3), self::totals(1)->add(self::totals(2)));
    }

    public function testPunctualityHasTheFourMeasuresAndTheBands(): void
    {
        $totals = new DayTotalsDto(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 0, 0, 0, 0, 0, 0);

        self::assertEquals(new PunctualityFiguresDto(
            new PunctualityCountDto(1, 2),
            new PunctualityCountDto(3, 4),
            new PunctualityCountDto(5, 6),
            new PunctualityCountDto(7, 8),
            new DelayBandsDto(7, 9, 10, 11),
        ), $totals->punctuality());
    }

    public function testSoloAndTrips(): void
    {
        $totals = new DayTotalsDto(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 12, 13, 14, 15, 16, 17);

        self::assertEquals(new SoloCountDto(16, 17), $totals->solo());
        self::assertEquals(new TripCountDto(12, 13, 14, 15, 9), $totals->trips(9));
    }

    /**
     * Gives totals with the same value in each counter.
     */
    private static function totals(int $value): DayTotalsDto
    {
        return new DayTotalsDto(...array_fill(0, \count(DayTotalsDto::FIELDS), $value));
    }
}

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
    public function testFromRowReadsTextAndNull(): void
    {
        $row = array_fill_keys(DayTotalsDto::FIELDS, 0);
        $totals = DayTotalsDto::fromRow(['trips' => '7', 'runs' => 4, 'soloRuns' => null, 'route' => '12', 'month' => '2026-01'] + $row);

        self::assertSame(7, $totals->trips);
        self::assertSame(4, $totals->runs);
        self::assertSame(0, $totals->soloRuns);
        self::assertSame(0, $totals->pickupOnTime);
    }

    /**
     * A query of the build gives only some counters. The other counters are 0.
     */
    public function testFromRowReadsOnlyTheGivenCounters(): void
    {
        $totals = DayTotalsDto::fromRow(['trips' => '7', 'runs' => 4], ['trips']);

        self::assertSame(7, $totals->trips);
        self::assertSame(0, $totals->runs);
    }

    /**
     * A wrong alias in a query must fail, not give 0.
     */
    public function testFromRowFailsOnAMissingCounter(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The query row has no counter "runs".');

        DayTotalsDto::fromRow(['trips' => 7], ['trips', 'runs']);
    }

    public function testFromRowFailsOnAValueThatIsNotANumber(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The counter "trips" is not a number.');

        DayTotalsDto::fromRow(['trips' => 'seven'], ['trips']);
    }

    public function testFromRowFailsOnAnUnknownCounter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Unknown counters: trip.');

        DayTotalsDto::fromRow(['trip' => 7], ['trip']);
    }

    public function testAddAddsEachCounter(): void
    {
        self::assertEquals(self::totals(3), self::totals(1)->add(self::totals(2)));
    }

    public function testPunctualityHasTheFourMeasuresAndTheBands(): void
    {
        $totals = new DayTotalsDto(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 0, 0, 0, 0, 0, 0, 0, 0, 0);

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
        $totals = new DayTotalsDto(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 12, 13, 14, 15, 16, 17, 18, 19, 20);

        self::assertEquals(new SoloCountDto(16, 17), $totals->solo());
        self::assertEquals(new TripCountDto(12, 13, 14, 15, 9), $totals->trips(9));
    }

    /**
     * The share of the cancelled trips is of all trips. No trips gives no value, not 0.
     */
    public function testCancelledPercentIsOfAllTrips(): void
    {
        self::assertSame(25.0, DayTotalsDto::fromRow(['trips' => 8, 'cancelledTrips' => 2], ['trips', 'cancelledTrips'])->cancelledPercent());
        self::assertNull(DayTotalsDto::empty()->cancelledPercent());
    }

    /**
     * Gives totals with the same value in each counter.
     */
    private static function totals(int $value): DayTotalsDto
    {
        return new DayTotalsDto(...array_fill(0, \count(DayTotalsDto::FIELDS), $value));
    }
}

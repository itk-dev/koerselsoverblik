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
     * The share of the late cancellations is also of all trips, so that it can be read against
     * the share of all cancelled trips. No trips gives no value, not 0.
     */
    public function testCancelledLatePercentIsOfAllTrips(): void
    {
        self::assertSame(12.5, self::cancellations(trips: 8, cancelled: 2, inTime: 1, late: 1)->cancelledLatePercent());
        self::assertSame(0.0, self::cancellations(trips: 8, cancelled: 2, inTime: 2, late: 0)->cancelledLatePercent());
        self::assertSame(0.0, self::cancellations(trips: 8, cancelled: 0, inTime: 0, late: 0)->cancelledLatePercent());
        self::assertNull(DayTotalsDto::empty()->cancelledLatePercent());
    }

    /**
     * Cancelled trips with no code are not "not late". If no cancelled trip has a code, the share
     * is not known, so it is null, not 0.
     */
    public function testCancelledLatePercentWithNoCodeIsNotKnown(): void
    {
        self::assertNull(self::cancellations(trips: 8, cancelled: 2, inTime: 0, late: 0)->cancelledLatePercent());
        self::assertNull(self::cancellations(trips: 8, cancelled: 2, inTime: 0, late: 0)->cancelledLateOfCancelledPercent());
    }

    /**
     * The note of the tile gives the late cancellations as a share of the cancelled trips. No
     * cancelled trips gives no value.
     */
    public function testCancelledLateOfCancelledPercent(): void
    {
        self::assertSame(25.0, self::cancellations(trips: 8, cancelled: 4, inTime: 3, late: 1)->cancelledLateOfCancelledPercent());
        self::assertNull(self::cancellations(trips: 8, cancelled: 0, inTime: 0, late: 0)->cancelledLateOfCancelledPercent());
    }

    /**
     * A cancelled trip with no code was cancelled neither in time nor too late.
     */
    public function testCancelledTripsWithNoCode(): void
    {
        $fields = ['cancelledTrips', 'cancelledInTimeTrips', 'cancelledLateTrips'];

        self::assertSame(2, DayTotalsDto::fromRow(array_combine($fields, [6, 3, 1]), $fields)->cancelledUnknownTrips());
        self::assertSame(0, DayTotalsDto::empty()->cancelledUnknownTrips());
    }

    private static function cancellations(int $trips, int $cancelled, int $inTime, int $late): DayTotalsDto
    {
        $fields = ['trips', 'cancelledTrips', 'cancelledInTimeTrips', 'cancelledLateTrips'];

        return DayTotalsDto::fromRow(array_combine($fields, [$trips, $cancelled, $inTime, $late]), $fields);
    }

    /**
     * Gives totals with the same value in each counter.
     */
    private static function totals(int $value): DayTotalsDto
    {
        return new DayTotalsDto(...array_fill(0, \count(DayTotalsDto::FIELDS), $value));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\ActivitySupplierRowDto;
use App\Report\DayTotalsDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks the completed trips for each run of one row of the supplier table of the activity page.
 * DayTotalsDtoTest checks the share of the cancelled trips.
 */
final class ActivitySupplierRowDtoTest extends TestCase
{
    public function testCompletedTripsForEachRun(): void
    {
        self::assertSame(2.0, self::row(trips: 8, completed: 6, cancelled: 2, runs: 3)->completedPerRun());
    }

    /**
     * A zero denominator gives no value, not 0.
     */
    public function testZeroGivesNoValue(): void
    {
        self::assertNull(self::row(trips: 0, completed: 0, cancelled: 0, runs: 0)->completedPerRun());
    }

    private static function row(int $trips, int $completed, int $cancelled, int $runs): ActivitySupplierRowDto
    {
        $fields = ['trips', 'completedTrips', 'cancelledTrips', 'notCancelledRuns'];

        return new ActivitySupplierRowDto('Vognmand A', DayTotalsDto::fromRow(array_combine($fields, [$trips, $completed, $cancelled, $runs]), $fields));
    }
}

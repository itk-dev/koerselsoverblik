<?php

declare(strict_types=1);

namespace App\Tests\Unit\Report;

use App\Report\ActivitySupplierRowDto;
use App\Report\DayTotalsDto;
use PHPUnit\Framework\TestCase;

/**
 * Checks the shares of one row of the supplier table of the activity page.
 */
final class ActivitySupplierRowDtoTest extends TestCase
{
    public function testCancelledShareIsOfAllTrips(): void
    {
        self::assertSame(25.0, self::row(trips: 8, completed: 6, cancelled: 2, runs: 3)->cancelledPercent());
    }

    public function testCompletedTripsForEachRun(): void
    {
        self::assertSame(2.0, self::row(trips: 8, completed: 6, cancelled: 2, runs: 3)->completedPerRun());
    }

    /**
     * A zero denominator gives no value, not 0.
     */
    public function testZeroGivesNoValue(): void
    {
        $row = self::row(trips: 0, completed: 0, cancelled: 0, runs: 0);

        self::assertNull($row->cancelledPercent());
        self::assertNull($row->completedPerRun());
    }

    private static function row(int $trips, int $completed, int $cancelled, int $runs): ActivitySupplierRowDto
    {
        $fields = ['trips', 'completedTrips', 'cancelledTrips', 'notCancelledRuns'];

        return new ActivitySupplierRowDto('Vognmand A', DayTotalsDto::fromRow(array_combine($fields, [$trips, $completed, $cancelled, $runs]), $fields));
    }
}

<?php

namespace App\Tests\Unit\Import\Extract;

use App\Import\Extract\StopRowDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use PHPUnit\Framework\TestCase;

/**
 * Checks that a stop row gets the converted values of a row with application types.
 *
 * @phpstan-import-type StopRowValues from StopRowDto
 */
final class StopRowDtoTest extends TestCase
{
    public function testValuesGiveTheStopRow(): void
    {
        $row = StopRowDto::fromValues(4, 'D-1', self::values());

        self::assertSame(4, $row->rowNumber);
        self::assertSame('D-1', $row->rowKey);
        self::assertSame('R-01', $row->route);
        self::assertSame('P-001', $row->personId);
        self::assertSame('2026-01-05 00:00:00', $row->serviceDate->format('Y-m-d H:i:s'));
        self::assertSame(3, $row->run);
        self::assertSame(StopType::Pickup, $row->stopType);
        self::assertSame(StopStatus::Cancelled, $row->stopStatus);
        self::assertSame(2, $row->passengersAfter);
        self::assertSame(3.5, $row->nextDistanceKm);
        self::assertSame(7.5, $row->nextDurationMin);
        self::assertSame('2026-01-05 07:10:00', $row->plannedTime->format('Y-m-d H:i:s'));
        self::assertNotNull($row->actualTime);
        self::assertSame('2026-01-05 07:12:00', $row->actualTime->format('Y-m-d H:i:s'));
        self::assertTrue($row->plannedSolo);
    }

    public function testEmptyValuesGiveNull(): void
    {
        $values = self::values();
        $values['person_id'] = null;
        $values['passengers_after'] = null;
        $values['next_distance_km'] = null;
        $values['next_duration_min'] = null;
        $values['actual_time'] = null;
        $values['planned_solo'] = null;

        $row = StopRowDto::fromValues(4, null, $values);

        self::assertNull($row->rowKey);
        self::assertNull($row->personId);
        self::assertNull($row->passengersAfter);
        self::assertNull($row->nextDistanceKm);
        self::assertNull($row->nextDurationMin);
        self::assertNull($row->actualTime);
        self::assertNull($row->plannedSolo);
    }

    public function testServiceDateFromADateAndTimeHasNoTime(): void
    {
        $values = self::values();
        $values['service_date'] = new \DateTimeImmutable('2026-01-05 13:45:10');

        $row = StopRowDto::fromValues(4, null, $values);

        self::assertSame('2026-01-05 00:00:00', $row->serviceDate->format('Y-m-d H:i:s'));
    }

    public function testTimeIsOnTheServiceDate(): void
    {
        $values = self::values();
        $values['service_date'] = new \DateTimeImmutable('2026-01-05 13:45:10');
        $values['planned_time'] = new \DateInterval('PT7H10M5S');
        $values['actual_time'] = new \DateInterval('PT7H12M');

        $row = StopRowDto::fromValues(4, null, $values);

        self::assertSame('2026-01-05 07:10:05', $row->plannedTime->format('Y-m-d H:i:s'));
        self::assertNotNull($row->actualTime);
        self::assertSame('2026-01-05 07:12:00', $row->actualTime->format('Y-m-d H:i:s'));
    }

    public function testTimeOf24OrMoreIsOnTheNextDay(): void
    {
        $values = self::values();
        $values['planned_time'] = new \DateInterval('PT25H10M');

        $row = StopRowDto::fromValues(4, null, $values);

        self::assertSame('2026-01-06 01:10:00', $row->plannedTime->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-05', $row->serviceDate->format('Y-m-d'));
    }

    /**
     * @return StopRowValues
     */
    private static function values(): array
    {
        return [
            'route' => 'R-01',
            'person_id' => 'P-001',
            'service_date' => new \DateTimeImmutable('2026-01-05'),
            'run' => 3,
            'stop_type' => 'pickup',
            'stop_status' => 'cancelled',
            'passengers_after' => 2,
            'next_distance_km' => 3.5,
            'next_duration_min' => 7.5,
            'planned_time' => new \DateTimeImmutable('2026-01-05 07:10'),
            'actual_time' => new \DateTimeImmutable('2026-01-05 07:12'),
            'planned_solo' => true,
        ];
    }
}

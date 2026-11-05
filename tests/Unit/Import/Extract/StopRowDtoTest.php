<?php

namespace App\Tests\Unit\Import\Extract;

use App\Import\Extract\StopRowDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks that a stop row gets the converted values of a row with application types.
 *
 * The times in the values are wall-clock times in Europe/Copenhagen. The stop row has them in
 * UTC.
 *
 * @phpstan-import-type StopRowValues from StopRowDto
 */
final class StopRowDtoTest extends TestCase
{
    public function testValuesGiveTheStopRow(): void
    {
        $row = self::fromValues(self::values(), 'D-1');

        self::assertSame(4, $row->rowNumber);
        self::assertSame('D-1', $row->rowKey);
        self::assertSame('R-01', $row->route);
        self::assertSame('P-001', $row->personId);
        self::assertUtc('2026-01-05 00:00:00', $row->serviceDate);
        self::assertSame(3, $row->run);
        self::assertSame(StopType::Pickup, $row->stopType);
        self::assertSame(StopStatus::Cancelled, $row->stopStatus);
        self::assertSame(2, $row->passengersAfter);
        self::assertSame(3.5, $row->nextDistanceKm);
        self::assertSame(7.5, $row->nextDurationMin);
        self::assertUtc('2026-01-05 06:10:00', $row->plannedTime);
        self::assertUtc('2026-01-05 06:12:00', $row->actualTime);
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

        $row = self::fromValues($values);

        self::assertNull($row->rowKey);
        self::assertNull($row->personId);
        self::assertNull($row->passengersAfter);
        self::assertNull($row->nextDistanceKm);
        self::assertNull($row->nextDurationMin);
        self::assertNull($row->actualTime);
        self::assertNull($row->plannedSolo);
    }

    /**
     * The service date is a calendar date. The time zone does not change it.
     */
    public function testServiceDateFromADateAndTimeHasNoTime(): void
    {
        $values = self::values();
        $values['service_date'] = new \DateTimeImmutable('2026-01-05 00:30:10', new \DateTimeZone('Europe/Copenhagen'));

        self::assertUtc('2026-01-05 00:00:00', self::fromValues($values)->serviceDate);
    }

    public function testTimeIsOnTheServiceDate(): void
    {
        $values = self::values();
        $values['service_date'] = new \DateTimeImmutable('2026-01-05 13:45:10');
        $values['planned_time'] = new \DateInterval('PT7H10M5S');
        $values['actual_time'] = new \DateInterval('PT7H12M');

        $row = self::fromValues($values);

        self::assertUtc('2026-01-05 06:10:05', $row->plannedTime);
        self::assertUtc('2026-01-05 06:12:00', $row->actualTime);
    }

    public function testTimeOf24OrMoreIsOnTheNextDay(): void
    {
        $values = self::values();
        $values['planned_time'] = new \DateInterval('PT25H10M');

        $row = self::fromValues($values);

        self::assertUtc('2026-01-06 00:10:00', $row->plannedTime);
        self::assertSame('2026-01-05', $row->serviceDate->format('Y-m-d'));
    }

    /**
     * The values from CellConverter are wall-clock times. The time zone of the value object is
     * not the time zone of the extract.
     */
    #[DataProvider('valueTimeZones')]
    public function testDateAndTimeIsAWallClockTime(string $timeZone): void
    {
        $values = self::values();
        $values['planned_time'] = new \DateTimeImmutable('2026-01-05 07:10', new \DateTimeZone($timeZone));

        self::assertUtc('2026-01-05 06:10:00', self::fromValues($values)->plannedTime);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function valueTimeZones(): iterable
    {
        yield 'UTC' => ['UTC'];
        yield 'another time zone' => ['America/New_York'];
    }

    /**
     * Copenhagen is two hours ahead of UTC in summer time, and one hour in winter time.
     */
    public function testSummerTime(): void
    {
        $values = self::values();
        $values['service_date'] = new \DateTimeImmutable('2026-06-15');
        $values['planned_time'] = new \DateInterval('PT7H10M');
        $values['actual_time'] = new \DateTimeImmutable('2026-06-15 07:12');

        $row = self::fromValues($values);

        self::assertUtc('2026-06-15 05:10:00', $row->plannedTime);
        self::assertUtc('2026-06-15 05:12:00', $row->actualTime);
    }

    /**
     * The clocks go forward from 02:00 to 03:00 on 29 March 2026, so 02:30 does not exist. PHP
     * reads it as 03:30 summer time. See "Times" in docs/architecture.md.
     */
    public function testTimeInTheGapOfTheChangeToSummerTimeIsOneHourLater(): void
    {
        $values = self::values();
        $values['service_date'] = new \DateTimeImmutable('2026-03-29');
        $values['planned_time'] = new \DateInterval('PT2H30M');
        $values['actual_time'] = new \DateTimeImmutable('2026-03-29 02:30');

        $row = self::fromValues($values);

        self::assertUtc('2026-03-29 01:30:00', $row->plannedTime);
        self::assertUtc('2026-03-29 01:30:00', $row->actualTime);
    }

    /**
     * The clocks go back from 03:00 to 02:00 on 25 October 2026, so 02:30 occurs two times. PHP
     * reads it as 02:30 winter time.
     */
    public function testTimeThatOccursTwoTimesIsInWinterTime(): void
    {
        $values = self::values();
        $values['service_date'] = new \DateTimeImmutable('2026-10-25');
        $values['planned_time'] = new \DateInterval('PT2H30M');
        $values['actual_time'] = new \DateTimeImmutable('2026-10-25 02:30');

        $row = self::fromValues($values);

        self::assertUtc('2026-10-25 01:30:00', $row->plannedTime);
        self::assertUtc('2026-10-25 01:30:00', $row->actualTime);
    }

    /**
     * @param StopRowValues $values
     */
    private static function fromValues(array $values, ?string $rowKey = null): StopRowDto
    {
        return StopRowDto::fromValues(4, $rowKey, $values, new \DateTimeZone('Europe/Copenhagen'));
    }

    private static function assertUtc(string $expected, ?\DateTimeImmutable $value): void
    {
        self::assertNotNull($value);
        self::assertSame($expected, $value->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $value->getTimezone()->getName());
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

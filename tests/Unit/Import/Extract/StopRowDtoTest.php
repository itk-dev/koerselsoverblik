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
 * The times in the values are wall-clock times. The stop row has them in the PHP default time
 * zone, Europe/Copenhagen in phpunit.dist.xml. See ADR 005.
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
        self::assertLocal('2026-01-05 00:00:00', $row->serviceDate);
        self::assertSame(3, $row->run);
        self::assertSame(StopType::Pickup, $row->stopType);
        self::assertSame(StopStatus::Cancelled, $row->stopStatus);
        self::assertSame(2, $row->passengersAfter);
        self::assertSame(3.5, $row->nextDistanceKm);
        self::assertSame(7.5, $row->nextDurationMin);
        self::assertLocal('2026-01-05 07:10:00', $row->plannedTime);
        self::assertLocal('2026-01-05 07:12:00', $row->actualTime);
        self::assertSame(120, $row->delaySeconds);
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
        self::assertNull($row->delaySeconds);
        self::assertNull($row->plannedSolo);
    }

    /**
     * The delay keeps its seconds. The figures compare it with the tolerance in minutes × 60, so
     * no rounding rule is necessary.
     */
    #[DataProvider('delays')]
    public function testDelayKeepsItsSeconds(string $actualTime, int $expected): void
    {
        $values = self::values();
        $values['planned_time'] = new \DateTimeImmutable('2026-01-05 07:10:00');
        $values['actual_time'] = new \DateTimeImmutable($actualTime);

        self::assertSame($expected, self::fromValues($values)->delaySeconds);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function delays(): iterable
    {
        yield 'exactly 5 minutes late' => ['2026-01-05 07:15:00', 300];
        yield 'one second more' => ['2026-01-05 07:15:01', 301];
        yield 'on the planned time' => ['2026-01-05 07:10:00', 0];
        yield 'early' => ['2026-01-05 07:08:30', -90];
    }

    /**
     * A planned time of 24:00 or more is on the next day. The registration of the driver has the
     * date of the next day.
     */
    public function testDelayAfterMidnightIsCorrect(): void
    {
        $values = self::values();
        $values['planned_time'] = new \DateInterval('PT24H30M');
        $values['actual_time'] = new \DateTimeImmutable('2026-01-06 00:33');

        self::assertSame(180, self::fromValues($values)->delaySeconds);
    }

    /**
     * The service date is a calendar date. The time zone of the value does not change it.
     */
    #[DataProvider('valueTimeZones')]
    public function testServiceDateFromADateAndTimeHasNoTime(string $timeZone): void
    {
        $values = self::values();
        $values['service_date'] = new \DateTimeImmutable('2026-01-05 00:30:10', new \DateTimeZone($timeZone));

        self::assertLocal('2026-01-05 00:00:00', self::fromValues($values)->serviceDate);
    }

    public function testTimeIsOnTheServiceDate(): void
    {
        $values = self::values();
        $values['service_date'] = new \DateTimeImmutable('2026-01-05 13:45:10');
        $values['planned_time'] = new \DateInterval('PT7H10M5S');
        $values['actual_time'] = new \DateInterval('PT7H12M');

        $row = self::fromValues($values);

        self::assertLocal('2026-01-05 07:10:05', $row->plannedTime);
        self::assertLocal('2026-01-05 07:12:00', $row->actualTime);
    }

    public function testTimeOf24OrMoreIsOnTheNextDay(): void
    {
        $values = self::values();
        $values['planned_time'] = new \DateInterval('PT25H10M');

        $row = self::fromValues($values);

        self::assertLocal('2026-01-06 01:10:00', $row->plannedTime);
        self::assertSame('2026-01-05', $row->serviceDate->format('Y-m-d'));
    }

    /**
     * The values from CellConverter are wall-clock times. StopRowDto does not use the time zone of
     * the value object.
     */
    #[DataProvider('valueTimeZones')]
    public function testDateAndTimeIsAWallClockTime(string $timeZone): void
    {
        $values = self::values();
        $values['planned_time'] = new \DateTimeImmutable('2026-01-05 07:10', new \DateTimeZone($timeZone));

        self::assertLocal('2026-01-05 07:10:00', self::fromValues($values)->plannedTime);
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
     * A time in summer time keeps its wall-clock time, with the offset of summer time.
     */
    public function testSummerTime(): void
    {
        $values = self::values();
        $values['service_date'] = new \DateTimeImmutable('2026-06-15');
        $values['planned_time'] = new \DateInterval('PT7H10M');
        $values['actual_time'] = new \DateTimeImmutable('2026-06-15 07:12');

        $row = self::fromValues($values);

        self::assertLocal('2026-06-15 07:10:00', $row->plannedTime);
        self::assertLocal('2026-06-15 07:12:00', $row->actualTime);
        self::assertSame('+02:00', $row->plannedTime->format('P'));
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

        self::assertLocal('2026-03-29 03:30:00', $row->plannedTime);
        self::assertLocal('2026-03-29 03:30:00', $row->actualTime);
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

        self::assertLocal('2026-10-25 02:30:00', $row->plannedTime);
        self::assertLocal('2026-10-25 02:30:00', $row->actualTime);
        self::assertSame('+01:00', $row->plannedTime->format('P'));
    }

    /**
     * The clocks go forward on 29 March 2026. From 00:30 to 03:30 local time is 2 hours.
     */
    public function testDelayAcrossTheChangeToSummerTimeIsCorrect(): void
    {
        $values = self::values();
        $values['service_date'] = new \DateTimeImmutable('2026-03-29');
        $values['planned_time'] = new \DateInterval('PT0H30M');
        $values['actual_time'] = new \DateInterval('PT3H30M');

        self::assertSame(7200, self::fromValues($values)->delaySeconds);
    }

    /**
     * @param StopRowValues $values
     */
    private static function fromValues(array $values, ?string $rowKey = null): StopRowDto
    {
        return StopRowDto::fromValues(4, $rowKey, $values);
    }

    private static function assertLocal(string $expected, ?\DateTimeImmutable $value): void
    {
        self::assertNotNull($value);
        self::assertSame($expected, $value->format('Y-m-d H:i:s'));
        self::assertSame('Europe/Copenhagen', $value->getTimezone()->getName());
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

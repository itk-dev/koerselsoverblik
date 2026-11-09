<?php

declare(strict_types=1);

namespace App\Import\Extract;

use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;

/**
 * One stop row of an extract, with application values.
 *
 * The mapping configuration makes sure that each field has a type that agrees with the
 * properties, and that the fields with no null value are required.
 *
 * @phpstan-type StopRowValues array{
 *     route: string,
 *     person_id: ?string,
 *     service_date: \DateTimeImmutable,
 *     run: int,
 *     stop_type: string,
 *     stop_status: string,
 *     passengers_after: ?int,
 *     next_distance_km: ?float,
 *     next_duration_min: ?float,
 *     planned_time: \DateInterval|\DateTimeImmutable,
 *     actual_time: \DateInterval|\DateTimeImmutable|null,
 *     planned_solo: ?bool,
 * }
 *
 * @phpstan-import-type SourceValue from SourceCells
 */
final readonly class StopRowDto
{
    /**
     * @param int                           $rowNumber    the 1-based row number in the sheet
     * @param ?string                       $rowKey       the values of the row id columns, or null if the mapping has no row id
     * @param array<array-key, SourceValue> $sourceValues the copy of the row, keyed by the column name
     * @param list<FieldWarningDto>         $warnings     the optional fields with a value that the parser cannot read
     */
    public function __construct(
        public int $rowNumber,
        public ?string $rowKey,
        public string $route,
        public ?string $personId,
        public \DateTimeImmutable $serviceDate,
        public int $run,
        public StopType $stopType,
        public StopStatus $stopStatus,
        public ?int $passengersAfter,
        public ?float $nextDistanceKm,
        public ?float $nextDurationMin,
        public \DateTimeImmutable $plannedTime,
        public ?\DateTimeImmutable $actualTime,
        public ?bool $plannedSolo,
        public array $sourceValues = [],
        public array $warnings = [],
    ) {
    }

    /**
     * The service date is the calendar date at 00:00, and the times are wall-clock times. Both are
     * in the PHP default time zone. See ADR 005.
     *
     * @param StopRowValues                 $values       the values from CellConverter, keyed by the FieldName value
     * @param array<array-key, SourceValue> $sourceValues the copy of the row, keyed by the column name
     * @param list<FieldWarningDto>         $warnings
     */
    public static function fromValues(int $rowNumber, ?string $rowKey, array $values, array $sourceValues = [], array $warnings = []): self
    {
        $serviceDate = self::serviceDate($values['service_date']);

        return new self(
            rowNumber: $rowNumber,
            rowKey: $rowKey,
            route: $values['route'],
            personId: $values['person_id'],
            serviceDate: $serviceDate,
            run: $values['run'],
            stopType: StopType::from($values['stop_type']),
            stopStatus: StopStatus::from($values['stop_status']),
            passengersAfter: $values['passengers_after'],
            nextDistanceKm: $values['next_distance_km'],
            nextDurationMin: $values['next_duration_min'],
            plannedTime: self::moment($values['planned_time'], $serviceDate),
            actualTime: null === $values['actual_time'] ? null : self::moment($values['actual_time'], $serviceDate),
            plannedSolo: $values['planned_solo'],
            sourceValues: $sourceValues,
            warnings: $warnings,
        );
    }

    /**
     * Gives the calendar date of the value of the date field, at 00:00 in the PHP default time
     * zone.
     */
    public static function serviceDate(\DateTimeImmutable $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value->format('Y-m-d'));
    }

    /**
     * Reads a wall-clock time in the PHP default time zone. See "Times" in docs/architecture.md.
     *
     * A time from a "time" field is on the service date. A time of 24:00 or more is on the next
     * day. OpenSpout gives the hours of a "time" field with no days, and never a negative time.
     */
    private static function moment(\DateInterval|\DateTimeImmutable $value, \DateTimeImmutable $serviceDate): \DateTimeImmutable
    {
        // UTC has no change to summer time. setTime() then gives the wall-clock time exactly, also
        // for a time of 24:00 or more.
        $wallClock = $value instanceof \DateInterval
            ? new \DateTimeImmutable($serviceDate->format('Y-m-d'), new \DateTimeZone('UTC'))->setTime($value->h, $value->i, $value->s)
            : $value;

        // The constructor and setTime() read a time in the hour that occurs two times
        // differently. The constructor is the only rule.
        return new \DateTimeImmutable($wallClock->format('Y-m-d H:i:s'));
    }
}

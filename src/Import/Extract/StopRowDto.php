<?php

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
 */
final readonly class StopRowDto
{
    /**
     * @param int     $rowNumber the 1-based row number in the sheet
     * @param ?string $rowKey    the values of the row id columns, or null if the mapping has no row id
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
    ) {
    }

    /**
     * @param StopRowValues $values the values from CellConverter, keyed by the FieldName value
     */
    public static function fromValues(int $rowNumber, ?string $rowKey, array $values): self
    {
        $serviceDate = $values['service_date']->setTime(0, 0);

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
        );
    }

    /**
     * A time from a "time" field is a wall-clock time on the service date. A time of 24:00 or
     * more is on the next day. See "Times" in docs/architecture.md.
     */
    private static function moment(\DateInterval|\DateTimeImmutable $value, \DateTimeImmutable $serviceDate): \DateTimeImmutable
    {
        return $value instanceof \DateInterval ? $serviceDate->setTime($value->h, $value->i, $value->s) : $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The counters of the figures for one route and one day, or the sum of the counters of more
 * routes and days. The citizens are not here, because the citizens of two days cannot be added.
 * See "Data model" in docs/architecture.md.
 */
final readonly class DayTotalsDto
{
    /**
     * The names of the counters, in the order of the constructor. The entity and the queries use
     * the same names.
     */
    public const array FIELDS = [
        'pickupOnTime',
        'pickupMeasured',
        'dropOffOnTime',
        'dropOffMeasured',
        'firstPickupOnTime',
        'firstPickupMeasured',
        'lastDropOnTime',
        'lastDropMeasured',
        'lastDropUpTo15',
        'lastDropUpTo30',
        'lastDropOver30',
        'trips',
        'completedTrips',
        'cancelledTrips',
        'pendingTrips',
        'runs',
        'soloRuns',
        'notCancelledRuns',
        'cancelledInTimeTrips',
        'cancelledLateTrips',
    ];

    /**
     * @param int $pickupOnTime         the measured pickups that are on time
     * @param int $pickupMeasured       the completed pickups with a delay
     * @param int $dropOffOnTime        the measured drop-offs that are on time
     * @param int $dropOffMeasured      the completed drop-offs with a delay
     * @param int $firstPickupOnTime    the measured first pickups of the route runs that are on time
     * @param int $firstPickupMeasured  the route runs with a delay at the first pickup
     * @param int $lastDropOnTime       the measured last drop-offs of the route runs that are on time
     * @param int $lastDropMeasured     the route runs with a delay at the last drop-off
     * @param int $lastDropUpTo15       the measured last drop-offs from the tolerance to 15 minutes late
     * @param int $lastDropUpTo30       the measured last drop-offs from 15 to 30 minutes late
     * @param int $lastDropOver30       the measured last drop-offs more than 30 minutes late
     * @param int $trips                the pickups, with any status
     * @param int $completedTrips       the completed pickups
     * @param int $cancelledTrips       the cancelled pickups
     * @param int $pendingTrips         the pickups with no registration
     * @param int $runs                 the route runs that are not cancelled and had a citizen in the car
     * @param int $soloRuns             the counted runs with exactly one citizen in the car at a time
     * @param int $notCancelledRuns     the route runs that are not cancelled, also a run with no citizen in the car
     * @param int $cancelledInTimeTrips the cancelled pickups that were cancelled in time
     * @param int $cancelledLateTrips   the cancelled pickups that were cancelled too late
     */
    public function __construct(
        public int $pickupOnTime,
        public int $pickupMeasured,
        public int $dropOffOnTime,
        public int $dropOffMeasured,
        public int $firstPickupOnTime,
        public int $firstPickupMeasured,
        public int $lastDropOnTime,
        public int $lastDropMeasured,
        public int $lastDropUpTo15,
        public int $lastDropUpTo30,
        public int $lastDropOver30,
        public int $trips,
        public int $completedTrips,
        public int $cancelledTrips,
        public int $pendingTrips,
        public int $runs,
        public int $soloRuns,
        public int $notCancelledRuns,
        public int $cancelledInTimeTrips,
        public int $cancelledLateTrips,
    ) {
    }

    public static function empty(): self
    {
        // FIELDS has the names of the parameters, so the keys are named arguments.
        return new self(...array_fill_keys(self::FIELDS, 0));
    }

    /**
     * Reads the counters from a query row. MariaDB gives a sum as text, and no sum (null) when no
     * row matches. A counter that is not in $fields is 0. A missing key fails, so that a wrong
     * alias in a query does not give zeros with no error.
     *
     * @param array<string, mixed> $row    keyed by the names in $fields, with other keys that are not read
     * @param list<string>         $fields the counters that the query gives, from FIELDS
     *
     * @throws \UnexpectedValueException if a counter of $fields is missing or is not a number
     */
    public static function fromRow(array $row, array $fields = self::FIELDS): self
    {
        if ([] !== $unknown = array_diff($fields, self::FIELDS)) {
            throw new \InvalidArgumentException(\sprintf('Unknown counters: %s.', implode(', ', $unknown)));
        }

        return new self(...array_map(static function (string $field) use ($row, $fields): int {
            if (!\in_array($field, $fields, true)) {
                return 0;
            }
            if (!\array_key_exists($field, $row)) {
                throw new \UnexpectedValueException(\sprintf('The query row has no counter "%s".', $field));
            }
            $value = $row[$field] ?? 0;
            if (!is_numeric($value)) {
                throw new \UnexpectedValueException(\sprintf('The counter "%s" is not a number.', $field));
            }

            return (int) $value;
        }, self::FIELDS));
    }

    public function add(self $other): self
    {
        return new self(
            $this->pickupOnTime + $other->pickupOnTime,
            $this->pickupMeasured + $other->pickupMeasured,
            $this->dropOffOnTime + $other->dropOffOnTime,
            $this->dropOffMeasured + $other->dropOffMeasured,
            $this->firstPickupOnTime + $other->firstPickupOnTime,
            $this->firstPickupMeasured + $other->firstPickupMeasured,
            $this->lastDropOnTime + $other->lastDropOnTime,
            $this->lastDropMeasured + $other->lastDropMeasured,
            $this->lastDropUpTo15 + $other->lastDropUpTo15,
            $this->lastDropUpTo30 + $other->lastDropUpTo30,
            $this->lastDropOver30 + $other->lastDropOver30,
            $this->trips + $other->trips,
            $this->completedTrips + $other->completedTrips,
            $this->cancelledTrips + $other->cancelledTrips,
            $this->pendingTrips + $other->pendingTrips,
            $this->runs + $other->runs,
            $this->soloRuns + $other->soloRuns,
            $this->notCancelledRuns + $other->notCancelledRuns,
            $this->cancelledInTimeTrips + $other->cancelledInTimeTrips,
            $this->cancelledLateTrips + $other->cancelledLateTrips,
        );
    }

    /**
     * Gives the four punctuality measures and the delay bands of the last drop-off.
     */
    public function punctuality(): PunctualityFiguresDto
    {
        return new PunctualityFiguresDto(
            new PunctualityCountDto($this->pickupOnTime, $this->pickupMeasured),
            new PunctualityCountDto($this->dropOffOnTime, $this->dropOffMeasured),
            new PunctualityCountDto($this->firstPickupOnTime, $this->firstPickupMeasured),
            new PunctualityCountDto($this->lastDropOnTime, $this->lastDropMeasured),
            new DelayBandsDto($this->lastDropOnTime, $this->lastDropUpTo15, $this->lastDropUpTo30, $this->lastDropOver30),
        );
    }

    /**
     * Gives the cancelled trips as a share of all trips in percent, or null if there are no trips.
     */
    public function cancelledPercent(): ?float
    {
        return 0 === $this->trips ? null : $this->cancelledTrips / $this->trips * 100;
    }

    /**
     * Gives the trips that were cancelled too late as a share of all trips in percent, or null if
     * there are no trips. The share is of all trips, so that it can be read against
     * cancelledPercent().
     */
    public function cancelledLatePercent(): ?float
    {
        return 0 === $this->trips ? null : $this->cancelledLateTrips / $this->trips * 100;
    }

    /**
     * Gives the cancelled trips with no code: cancelled neither in time nor too late.
     */
    public function cancelledUnknownTrips(): int
    {
        return $this->cancelledTrips - $this->cancelledInTimeTrips - $this->cancelledLateTrips;
    }

    public function solo(): SoloCountDto
    {
        return new SoloCountDto($this->runs, $this->soloRuns);
    }

    /**
     * @param int $citizens the citizens who were driven in the same range, from the stop rows
     */
    public function trips(int $citizens): TripCountDto
    {
        return new TripCountDto($this->trips, $this->completedTrips, $this->cancelledTrips, $this->pendingTrips, $citizens);
    }
}

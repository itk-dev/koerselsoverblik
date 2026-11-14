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
    ];

    /**
     * @param int $pickupOnTime        the measured pickups that are on time
     * @param int $pickupMeasured      the completed pickups with a delay
     * @param int $dropOffOnTime       the measured drop-offs that are on time
     * @param int $dropOffMeasured     the completed drop-offs with a delay
     * @param int $firstPickupOnTime   the measured first pickups of the route runs that are on time
     * @param int $firstPickupMeasured the route runs with a delay at the first pickup
     * @param int $lastDropOnTime      the measured last drop-offs of the route runs that are on time
     * @param int $lastDropMeasured    the route runs with a delay at the last drop-off
     * @param int $lastDropUpTo15      the measured last drop-offs from the tolerance to 15 minutes late
     * @param int $lastDropUpTo30      the measured last drop-offs from 15 to 30 minutes late
     * @param int $lastDropOver30      the measured last drop-offs more than 30 minutes late
     * @param int $trips               the pickups, with any status
     * @param int $completedTrips      the completed pickups
     * @param int $cancelledTrips      the cancelled pickups
     * @param int $pendingTrips        the pickups with no registration
     * @param int $runs                the route runs that are not cancelled and had a citizen in the car
     * @param int $soloRuns            the counted runs with exactly one citizen in the car at a time
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
    ) {
    }

    public static function empty(): self
    {
        return new self(...array_fill(0, \count(self::FIELDS), 0));
    }

    /**
     * Reads the counters from a query row. MariaDB gives a sum as text, and no sum when no row
     * matches.
     *
     * @param array<string, mixed> $row keyed by the names in FIELDS, with other keys that are not read
     */
    public static function fromRow(array $row): self
    {
        return new self(...array_map(static function (string $field) use ($row): int {
            $value = $row[$field] ?? 0;

            return is_numeric($value) ? (int) $value : 0;
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

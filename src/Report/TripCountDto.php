<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The trips and the citizens of a date range. A trip is a pickup, also a cancelled pickup and a
 * pickup with no registration. The status of the pickup gives the status of the trip.
 */
final readonly class TripCountDto
{
    /**
     * @param int $trips     the pickups
     * @param int $completed the pickups with the status "completed"
     * @param int $cancelled the pickups with the status "cancelled"
     * @param int $pending   the pickups with no registration
     * @param int $citizens  the distinct person ids of the pickups that are not cancelled: the citizens who were driven
     */
    public function __construct(
        public int $trips,
        public int $completed,
        public int $cancelled,
        public int $pending,
        public int $citizens,
    ) {
    }
}

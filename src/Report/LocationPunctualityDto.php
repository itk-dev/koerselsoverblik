<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The punctuality of the stops at one location. A location is only a group of stops. It is never
 * shown with a person id.
 */
final readonly class LocationPunctualityDto
{
    /**
     * @param string              $location the location name: the institution of the stops
     * @param PunctualityCountDto $stops    the measured pickups and drop-offs at the location
     */
    public function __construct(
        public string $location,
        public PunctualityCountDto $stops,
    ) {
    }
}

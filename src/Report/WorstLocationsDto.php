<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The locations with the lowest punctuality, and the locations that have too few measured stops
 * to be ranked.
 */
final readonly class WorstLocationsDto
{
    /**
     * @param list<LocationPunctualityDto> $locations the ranked locations, the worst first
     * @param int                          $leftOut   the number of locations with fewer measured stops than the minimum
     * @param int                          $minStops  the minimum number of measured stops of a ranked location
     */
    public function __construct(
        public array $locations,
        public int $leftOut,
        public int $minStops,
    ) {
    }
}

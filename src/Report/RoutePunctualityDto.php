<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The punctuality at the last drop-off of one route with one supplier.
 */
final readonly class RoutePunctualityDto
{
    /**
     * @param string  $route       the route name
     * @param ?string $supplier    the name of the supplier, or null for "Unknown"
     * @param PunctualityCountDto $lastDropOff the measured last drop-offs of the runs of the route
     */
    public function __construct(
        public string $route,
        public ?string $supplier,
        public PunctualityCountDto $lastDropOff,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The punctuality figures of a period.
 */
final readonly class PunctualityReportDto
{
    /**
     * @param list<SupplierPunctualityDto> $suppliers   the figures of each supplier, in the order of the name, with "Unknown" last
     * @param list<RoutePunctualityDto>    $worstRoutes the routes with the lowest punctuality at the last drop-off, the worst first
     */
    public function __construct(
        public PunctualityFiguresDto $total,
        public array $suppliers,
        public array $worstRoutes,
    ) {
    }
}

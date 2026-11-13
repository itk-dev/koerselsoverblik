<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;

/**
 * Calculates the figures of the overview: the month of the page and the months before it, for
 * the sparklines.
 */
final readonly class OverviewPageBuilder
{
    /**
     * The number of months in the sparklines, the same as on the punctuality page.
     */
    public const int MONTHS = PunctualityPageBuilder::MONTHS;

    public function __construct(
        private StopRowRepository $stopRows,
        private RouteRunRepository $routeRuns,
    ) {
    }

    public function build(MonthDto $month): OverviewPageDto
    {
        $months = $month->trailing(self::MONTHS);

        return new OverviewPageDto(
            $months,
            array_map(fn (MonthDto $item): TripCountDto => $this->stopRows->countTrips($item->range()), $months),
            array_map(fn (MonthDto $item): SoloCountDto => $this->routeRuns->countSoloRuns($item->range()), $months),
        );
    }
}

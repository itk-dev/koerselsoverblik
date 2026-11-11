<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;

/**
 * Calculates the figures of the overview: the selected period, the comparison period, and the 12
 * months up to the period for the sparklines.
 */
final readonly class OverviewPageBuilder
{
    public function __construct(
        private StopRowRepository $stopRows,
        private RouteRunRepository $routeRuns,
    ) {
    }

    public function build(PeriodSelectionDto $selection): OverviewPageDto
    {
        $months = $selection->months;
        $comparison = $selection->comparisonRange;

        return new OverviewPageDto(
            $months,
            array_map(fn (MonthDto $item): TripCountDto => $this->stopRows->countTrips($item->range()), $months),
            array_map(fn (MonthDto $item): SoloCountDto => $this->routeRuns->countSoloRuns($item->range()), $months),
            $this->stopRows->countTrips($selection->selectedRange),
            null === $comparison ? null : $this->stopRows->countTrips($comparison),
            $this->routeRuns->countSoloRuns($selection->selectedRange),
            null === $comparison ? null : $this->routeRuns->countSoloRuns($comparison),
        );
    }
}

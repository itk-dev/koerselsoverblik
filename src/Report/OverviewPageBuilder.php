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
        $supplier = $selection->filter->supplier;
        // A comparison that starts before the data has fewer days. A change of a count then only
        // shows the extra days. A share has no such fault, so it is still compared.
        $compareCounts = null !== $comparison && !$selection->comparison->startsBeforeData;

        return new OverviewPageDto(
            $months,
            array_map(fn (MonthDto $item): TripCountDto => $this->stopRows->countTrips($item->range(), $supplier), $months),
            array_map(fn (MonthDto $item): SoloCountDto => $this->routeRuns->countSoloRuns($item->range(), $supplier), $months),
            $this->stopRows->countTrips($selection->selectedRange, $supplier),
            $compareCounts ? $this->stopRows->countTrips($comparison, $supplier) : null,
            $this->routeRuns->countSoloRuns($selection->selectedRange, $supplier),
            null === $comparison ? null : $this->routeRuns->countSoloRuns($comparison, $supplier),
        );
    }
}

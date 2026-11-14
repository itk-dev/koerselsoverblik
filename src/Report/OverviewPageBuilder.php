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
        $selected = $selection->selectedRange;
        $comparison = $selection->comparisonRange;
        $supplier = $selection->filter->supplier;
        // A comparison that starts before the data has fewer days. A change of a count then only
        // shows the extra days. A share has no such fault, so it is still compared.
        $compareCounts = null !== $comparison && !$selection->comparison->startsBeforeData;

        // One query for each figure gives all months.
        $tripsByMonth = $this->stopRows->countTripsByMonth($selection->monthsRange(), $supplier);
        $solosByMonth = $this->routeRuns->countSoloRunsByMonth($selection->monthsRange(), $supplier);
        $trips = array_map(static fn (MonthDto $item): TripCountDto => $tripsByMonth[$item->query()] ?? new TripCountDto(0, 0, 0, 0, 0), $months);
        $solos = array_map(static fn (MonthDto $item): SoloCountDto => $solosByMonth[$item->query()] ?? new SoloCountDto(0, 0), $months);

        // A period that is a month of the history uses the figures of that month.
        $selectedMonth = $selection->monthIndexOf($selected);
        $comparisonMonth = $selection->monthIndexOf($comparison);

        return new OverviewPageDto(
            $months,
            $trips,
            $solos,
            null === $selectedMonth ? $this->stopRows->countTrips($selected, $supplier) : $trips[$selectedMonth],
            match (true) {
                !$compareCounts => null,
                null !== $comparisonMonth => $trips[$comparisonMonth],
                default => $this->stopRows->countTrips($comparison, $supplier),
            },
            null === $selectedMonth ? $this->routeRuns->countSoloRuns($selected, $supplier) : $solos[$selectedMonth],
            match (true) {
                null === $comparison => null,
                null !== $comparisonMonth => $solos[$comparisonMonth],
                default => $this->routeRuns->countSoloRuns($comparison, $supplier),
            },
        );
    }
}

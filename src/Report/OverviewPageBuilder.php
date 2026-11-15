<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\MonthCitizensRepository;
use App\Repository\RouteDayTotalsRepository;
use App\Repository\StopRowRepository;

/**
 * Calculates the figures of the overview: the selected period, the comparison period, and the 12
 * months up to the period for the sparklines.
 */
final readonly class OverviewPageBuilder
{
    public function __construct(
        private RouteDayTotalsRepository $totals,
        private StopRowRepository $stopRows,
        private MonthCitizensRepository $monthCitizens,
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
        $compareCounts = !$selection->comparison->startsBeforeData;

        // The totals of each route and day give the trips and the solo runs. The citizens are
        // distinct person ids, so they cannot be added. The import counts them for each month,
        // and a period that is not a month counts them on the stop rows.
        $totalsByMonth = $this->totals->sumByMonth($selection->monthsRange(), $supplier);
        $citizensByMonth = $this->monthCitizens->byMonth($selection->monthsRange(), $supplier);
        $monthTotals = array_map(static fn (MonthDto $item): DayTotalsDto => $totalsByMonth[$item->query()] ?? DayTotalsDto::empty(), $months);
        $trips = array_map(static fn (MonthDto $item, DayTotalsDto $totals): TripCountDto => $totals->trips($citizensByMonth[$item->query()] ?? 0), $months, $monthTotals);
        $solos = array_map(static fn (DayTotalsDto $totals): SoloCountDto => $totals->solo(), $monthTotals);

        // A period that is a month of the history uses the figures of that month.
        $tripsOf = fn (DateRangeDto $range): TripCountDto => null === ($month = $selection->monthIndexOf($range))
            ? $this->totals->sum($range, $supplier)->trips($this->stopRows->countCitizens($range, $supplier))
            : $trips[$month];
        $soloOf = fn (DateRangeDto $range): SoloCountDto => null === ($month = $selection->monthIndexOf($range))
            ? $this->totals->sum($range, $supplier)->solo()
            : $solos[$month];

        return new OverviewPageDto(
            $months,
            $trips,
            $solos,
            $tripsOf($selected),
            null !== $comparison && $compareCounts ? $tripsOf($comparison) : null,
            $soloOf($selected),
            null === $comparison ? null : $soloOf($comparison),
        );
    }
}

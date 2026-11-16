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
        $comparison = $selection->comparisonRange;
        $supplier = $selection->filter->supplier;

        // The totals of each route and day give the trips and the solo runs. The citizens are
        // distinct person ids, so they cannot be added. The import counts them for each month,
        // and a period that is not a month counts them on the stop rows.
        $totalsByMonth = $this->totals->sumByMonth($selection->monthsRange(), $supplier);
        $citizensByMonth = $this->monthCitizens->byMonth($selection->monthsRange(), $supplier);
        $monthTotals = array_map(static fn (MonthDto $item): DayTotalsDto => $totalsByMonth[$item->query()] ?? DayTotalsDto::empty(), $months);
        $trips = array_map(static fn (MonthDto $item, DayTotalsDto $totals): TripCountDto => $totals->trips($citizensByMonth[$item->query()] ?? 0), $months, $monthTotals);
        $solos = array_map(static fn (DayTotalsDto $totals): SoloCountDto => $totals->solo(), $monthTotals);

        // Each period reads its totals once, for the trips and the solo runs.
        $selectedTotals = $this->totalsOf($selection, $selection->selectedRange, $monthTotals);
        $comparisonTrips = null;
        $comparisonSolo = null;
        if (null !== $comparison) {
            $comparisonTotals = $this->totalsOf($selection, $comparison, $monthTotals);
            $comparisonSolo = $comparisonTotals->solo();
            // A comparison that starts before the data has fewer days. A change of a count then
            // only shows the extra days. A share has no such fault, so it is still compared.
            if (!$selection->comparison->startsBeforeData) {
                $comparisonTrips = $comparisonTotals->trips($this->citizensOf($selection, $comparison, $citizensByMonth));
            }
        }

        return new OverviewPageDto(
            $months,
            $trips,
            $solos,
            $selectedTotals->trips($this->citizensOf($selection, $selection->selectedRange, $citizensByMonth)),
            $comparisonTrips,
            $selectedTotals->solo(),
            $comparisonSolo,
        );
    }

    /**
     * Gives the totals of a period. A period that is a month of the history uses the totals of
     * that month.
     *
     * @param list<DayTotalsDto> $monthTotals in the order of the months of the selection
     */
    private function totalsOf(PeriodSelectionDto $selection, DateRangeDto $range, array $monthTotals): DayTotalsDto
    {
        $month = $selection->monthIndexOf($range);

        return null === $month ? $this->totals->sum($range, $selection->filter->supplier) : $monthTotals[$month];
    }

    /**
     * Gives the citizens of a period, with the same rule as totalsOf().
     *
     * @param array<string, int> $citizensByMonth keyed by the month as YYYY-MM, as MonthDto::query()
     */
    private function citizensOf(PeriodSelectionDto $selection, DateRangeDto $range, array $citizensByMonth): int
    {
        $month = $selection->monthIndexOf($range);

        return null === $month
            ? $this->stopRows->countCitizens($range, $selection->filter->supplier)
            : $citizensByMonth[$selection->months[$month]->query()] ?? 0;
    }
}

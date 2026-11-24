<?php

declare(strict_types=1);

namespace App\Report;

use App\Repository\RouteDayTotalsRepository;

/**
 * Calculates the figures of the activity page: the selected period, the comparison period, and the
 * 12 months up to the period for the sparklines and the charts.
 */
final readonly class ActivityPageBuilder
{
    public function __construct(
        private RouteDayTotalsRepository $totals,
    ) {
    }

    public function build(PeriodSelectionDto $selection): ActivityPageDto
    {
        $totalsByMonth = $this->totals->sumByMonth($selection->monthsRange(), $selection->filter->supplier);
        $monthTotals = array_map(static fn (MonthDto $item): DayTotalsDto => $totalsByMonth[$item->query()] ?? DayTotalsDto::empty(), $selection->months);
        $comparison = null === $selection->comparisonRange ? null : $this->totalsOf($selection, $selection->comparisonRange, $monthTotals);

        return new ActivityPageDto(
            $selection->months,
            $monthTotals,
            $this->totalsOf($selection, $selection->selectedRange, $monthTotals),
            $comparison,
            $selection->comparison->startsBeforeData,
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
}

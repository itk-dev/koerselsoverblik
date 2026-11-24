<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The figures of the activity page: the totals of the selected period, of the comparison period,
 * and of the 12 months up to the period for the sparklines and the charts.
 */
final readonly class ActivityPageDto
{
    /**
     * @param non-empty-list<MonthDto>     $months              the months of the history, the oldest first
     * @param non-empty-list<DayTotalsDto> $monthTotals         the totals of each month, in the order of the months
     * @param DayTotalsDto                 $selected            the totals of the selected period
     * @param ?DayTotalsDto                $comparison          the totals of the comparison period, or null if it has no data
     * @param bool                         $comparisonIsPartial true if the comparison period starts before the data, so that its counts are not compared
     */
    public function __construct(
        public array $months,
        public array $monthTotals,
        public DayTotalsDto $selected,
        public ?DayTotalsDto $comparison,
        public bool $comparisonIsPartial,
    ) {
    }

    public function trips(): CountTrendDto
    {
        return $this->trend(static fn (DayTotalsDto $totals): int => $totals->trips);
    }

    public function completed(): CountTrendDto
    {
        return $this->trend(static fn (DayTotalsDto $totals): int => $totals->completedTrips);
    }

    /**
     * The route runs that are not cancelled, also a run with no citizen in the car.
     */
    public function runs(): CountTrendDto
    {
        return $this->trend(static fn (DayTotalsDto $totals): int => $totals->notCancelledRuns);
    }

    /**
     * Gives the share of the cancelled trips of all trips, with the change in percentage points.
     * A share is also compared with a comparison period that starts before the data.
     */
    public function cancelled(): ShareTrendDto
    {
        $current = $this->selected->cancelledPercent();
        $before = $this->comparison?->cancelledPercent();

        return new ShareTrendDto(
            $current,
            null === $current || null === $before ? null : $current - $before,
            array_map(static fn (DayTotalsDto $totals): ?float => $totals->cancelledPercent(), $this->monthTotals),
        );
    }

    /**
     * A period with no trips has no data, so its value is null, not 0.
     *
     * @param \Closure(DayTotalsDto): int $value
     */
    private function trend(\Closure $value): CountTrendDto
    {
        $valueOf = static fn (?DayTotalsDto $totals): ?int => null === $totals || 0 === $totals->trips ? null : $value($totals);
        $current = $valueOf($this->selected);
        $before = $this->comparisonIsPartial ? null : $valueOf($this->comparison);

        return new CountTrendDto(
            $current,
            null === $current || null === $before ? null : $current - $before,
            array_map(static fn (DayTotalsDto $totals): ?float => null === ($item = $valueOf($totals)) ? null : (float) $item, $this->monthTotals),
        );
    }
}

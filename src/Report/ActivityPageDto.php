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
     */
    public function cancelled(): ShareTrendDto
    {
        return $this->share(static fn (DayTotalsDto $totals): ?float => $totals->cancelledPercent());
    }

    /**
     * Gives the share of the trips that were cancelled too late, of all trips, with the change in
     * percentage points.
     */
    public function cancelledLate(): ShareTrendDto
    {
        return $this->share(static fn (DayTotalsDto $totals): ?float => $totals->cancelledLatePercent());
    }

    /**
     * Tells if the selected period or a month has cancelled trips with no code. Only then do the
     * bar and the columns show them as a part.
     */
    public function hasCancelledWithNoCode(): bool
    {
        return array_any([$this->selected, ...$this->monthTotals], static fn (DayTotalsDto $totals): bool => $totals->cancelledUnknownTrips() > 0);
    }

    /**
     * A share is also compared with a comparison period that starts before the data.
     *
     * @param \Closure(DayTotalsDto): ?float $value
     */
    private function share(\Closure $value): ShareTrendDto
    {
        $current = $value($this->selected);
        $before = null === $this->comparison ? null : $value($this->comparison);

        return new ShareTrendDto(
            $current,
            null === $current || null === $before ? null : $current - $before,
            array_map($value, $this->monthTotals),
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

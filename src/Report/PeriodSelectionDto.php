<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The periods of a page: the selected period, the comparison period and the 12 months of the
 * trend, with the links to the previous and the next period.
 */
final readonly class PeriodSelectionDto
{
    /**
     * @param DateRangeDto             $comparisonRange the dates of the comparison period, cut to the number of days of a period that has not ended
     * @param \DateTimeImmutable       $previousAnchor  a date in the previous period
     * @param ?\DateTimeImmutable      $nextAnchor      a date in the next period, or null if the next period starts after today
     * @param non-empty-list<MonthDto> $months          the 12 months up to the month of the period, the oldest first
     */
    public function __construct(
        public PeriodFilter $filter,
        public PeriodDto $selected,
        public PeriodDto $comparison,
        public DateRangeDto $comparisonRange,
        public \DateTimeImmutable $previousAnchor,
        public ?\DateTimeImmutable $nextAnchor,
        public array $months,
    ) {
    }

    /**
     * Tells if the comparison has fewer days than its period, because the selected period has not
     * ended.
     */
    public function isComparisonCut(): bool
    {
        return $this->comparisonRange->lastDate < $this->comparison->range->lastDate;
    }
}

<?php

declare(strict_types=1);

namespace App\Report;

use App\Entity\Supplier;

/**
 * The periods of a page: the selected period, the comparison period and the 12 months of the
 * trend, with the links to the previous and the next period, and the suppliers of the filter bar.
 */
final readonly class PeriodSelectionDto
{
    /**
     * @param DateRangeDto             $selectedRange   the dates of the selected period, from the start of the data up to today
     * @param ?DateRangeDto            $comparisonRange the dates of the comparison period, cut to the number of days of a selected period that has not ended, or null if the comparison period has no data
     * @param ?\DateTimeImmutable      $previousAnchor  the first date of the previous period, or null if the previous period ends before the start of the data
     * @param ?\DateTimeImmutable      $nextAnchor      the first date of the next period, or null if the next period starts after today
     * @param non-empty-list<MonthDto> $months          the 12 months up to the month of the last date of the selected range, the oldest first
     * @param list<Supplier>           $suppliers       the suppliers of the filter bar, in the order of the name
     */
    public function __construct(
        public PeriodFilter $filter,
        public PeriodDto $selected,
        public DateRangeDto $selectedRange,
        public PeriodDto $comparison,
        public ?DateRangeDto $comparisonRange,
        public ?\DateTimeImmutable $previousAnchor,
        public ?\DateTimeImmutable $nextAnchor,
        public array $months,
        public array $suppliers = [],
    ) {
    }

    /**
     * Gives the selection with the suppliers of the filter bar. The factory of the periods does
     * not read the database, so PeriodSelector adds them.
     *
     * @param list<Supplier> $suppliers
     */
    public function withSuppliers(array $suppliers): self
    {
        return clone ($this, ['suppliers' => $suppliers]);
    }

    /**
     * Gives the dates of the 12 months, from the first date of the oldest month to the last date
     * of the newest month.
     */
    public function monthsRange(): DateRangeDto
    {
        return DateRangeDto::create($this->months[0]->range()->firstDate, $this->months[\count($this->months) - 1]->range()->lastDate);
    }

    /**
     * Gives the position of the month that has the same dates as the range, so that a page can
     * use the figures of that month and not calculate them again.
     *
     * @return ?int the index in the months, or null if no month has the same dates
     */
    public function monthIndexOf(?DateRangeDto $range): ?int
    {
        if (null === $range) {
            return null;
        }
        foreach ($this->months as $index => $month) {
            if ($month->range()->equals($range)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Tells if the comparison has fewer days than its period, because the selected period has not
     * ended.
     */
    public function isComparisonCut(): bool
    {
        // The comparison range is null when the comparison period has no range.
        return null !== $this->comparisonRange && $this->comparisonRange->lastDate < $this->comparison->range?->lastDate;
    }
}

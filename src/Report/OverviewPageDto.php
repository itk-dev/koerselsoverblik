<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The figures of the overview: the selected period, the comparison period, and the 12 months up
 * to the period for the sparklines.
 */
final readonly class OverviewPageDto
{
    /**
     * @param non-empty-list<MonthDto>     $months         the months of the history, the oldest first
     * @param non-empty-list<TripCountDto> $counts         the trips and the citizens of each month, in the order of the months
     * @param non-empty-list<SoloCountDto> $solos          the real solo runs of each month, in the order of the months
     * @param TripCountDto                 $selected       the trips and the citizens of the selected period
     * @param ?TripCountDto                $comparison     the trips and the citizens of the comparison period, or null if it has no data or starts before the data
     * @param SoloCountDto                 $selectedSolo   the real solo runs of the selected period
     * @param ?SoloCountDto                $comparisonSolo the real solo runs of the comparison period, or null if it has no data
     */
    public function __construct(
        public array $months,
        public array $counts,
        public array $solos,
        public TripCountDto $selected,
        public ?TripCountDto $comparison,
        public SoloCountDto $selectedSolo,
        public ?SoloCountDto $comparisonSolo,
    ) {
    }

    public function current(): TripCountDto
    {
        return $this->selected;
    }

    public function currentSolo(): SoloCountDto
    {
        return $this->selectedSolo;
    }

    /**
     * Gives the share of the real solo runs, with the change in percentage points.
     */
    public function realSolo(): ShareTrendDto
    {
        $current = $this->selectedSolo->percent();
        $before = $this->comparisonSolo?->percent();

        return new ShareTrendDto(
            $current,
            null === $current || null === $before ? null : $current - $before,
            array_map(static fn (SoloCountDto $solo): ?float => $solo->percent(), $this->solos),
        );
    }

    public function trips(): CountTrendDto
    {
        return $this->trend(static fn (TripCountDto $count): int => $count->trips);
    }

    public function citizens(): CountTrendDto
    {
        return $this->trend(static fn (TripCountDto $count): int => $count->citizens);
    }

    /**
     * A period with no trips has no data, so its value is null, not 0.
     *
     * @param \Closure(TripCountDto): int $value
     */
    private function trend(\Closure $value): CountTrendDto
    {
        $valueOf = static fn (?TripCountDto $count): ?int => null === $count || 0 === $count->trips ? null : $value($count);
        $current = $valueOf($this->selected);
        $before = $valueOf($this->comparison);

        return new CountTrendDto(
            $current,
            null === $current || null === $before ? null : $current - $before,
            array_map(static fn (TripCountDto $count): ?float => null === ($item = $valueOf($count)) ? null : (float) $item, $this->counts),
        );
    }
}

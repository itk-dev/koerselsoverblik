<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The figures of the overview for a month, with the months before it for the sparklines.
 */
final readonly class OverviewPageDto
{
    /**
     * @param non-empty-list<MonthDto>     $months the months, the oldest first. The last month is the month of the page
     * @param non-empty-list<TripCountDto> $counts the trips and the citizens of each month, in the order of the months
     */
    public function __construct(
        public array $months,
        public array $counts,
    ) {
    }

    public function month(): MonthDto
    {
        return $this->months[\count($this->months) - 1];
    }

    public function current(): TripCountDto
    {
        return $this->counts[\count($this->counts) - 1];
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
     * A month with no trips has no data, so its value is null, not 0.
     *
     * @param \Closure(TripCountDto): int $value
     */
    private function trend(\Closure $value): CountTrendDto
    {
        $history = array_map(static fn (TripCountDto $count): ?int => 0 === $count->trips ? null : $value($count), $this->counts);
        $current = $history[\count($history) - 1];
        $before = $history[\count($history) - 2] ?? null;

        return new CountTrendDto(
            $current,
            null === $current || null === $before ? null : $current - $before,
            array_map(static fn (?int $item): ?float => null === $item ? null : (float) $item, $history),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Report;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Calculates the figures of the punctuality page: the selected period, the comparison period, and
 * the 12 months up to the period for the sparklines and the charts.
 */
final readonly class PunctualityPageBuilder
{
    /**
     * @param int $target the value of APP_PUNCTUALITY_TARGET
     */
    public function __construct(
        private PunctualityCalculator $calculator,
        #[Autowire(param: 'app.punctuality_target')]
        private int $target,
    ) {
    }

    public function build(PeriodSelectionDto $selection): PunctualityPageDto
    {
        $supplier = $selection->filter->supplier;
        // The months and the comparison only need the total. Only the selected period needs the
        // figures of each supplier and route.
        $byMonth = $this->calculator->totalsByMonth($selection->monthsRange(), $supplier);
        $history = array_map(static fn (MonthDto $item): PunctualityFiguresDto => $byMonth[$item->query()] ?? PunctualityFiguresDto::empty(), $selection->months);

        return new PunctualityPageDto(
            $selection->months,
            $history,
            $this->calculator->calculate($selection->selectedRange, $supplier),
            $this->comparison($selection, $history),
            $this->target,
        );
    }

    /**
     * @param non-empty-list<PunctualityFiguresDto> $history
     */
    private function comparison(PeriodSelectionDto $selection, array $history): ?PunctualityFiguresDto
    {
        $range = $selection->comparisonRange;
        if (null === $range) {
            return null;
        }
        $month = $selection->monthIndexOf($range);

        return null === $month ? $this->calculator->total($range, $selection->filter->supplier) : $history[$month];
    }
}

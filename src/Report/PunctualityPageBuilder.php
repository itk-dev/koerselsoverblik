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
        return new PunctualityPageDto(
            $selection->months,
            array_map(fn (MonthDto $item): PunctualityReportDto => $this->calculator->calculate($item->range()), $selection->months),
            $this->calculator->calculate($selection->selectedRange),
            null === $selection->comparisonRange ? null : $this->calculator->calculate($selection->comparisonRange),
            $this->target,
        );
    }
}

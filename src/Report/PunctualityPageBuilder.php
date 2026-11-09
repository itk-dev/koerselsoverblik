<?php

declare(strict_types=1);

namespace App\Report;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Calculates the figures of the punctuality page: the month of the page and the months before it,
 * for the sparklines and the charts.
 */
final readonly class PunctualityPageBuilder
{
    /**
     * The number of months in the sparklines and the charts.
     */
    public const int MONTHS = 12;

    /**
     * @param int $target the value of APP_PUNCTUALITY_TARGET
     */
    public function __construct(
        private PunctualityCalculator $calculator,
        #[Autowire(param: 'app.punctuality_target')]
        private int $target,
    ) {
    }

    public function build(MonthDto $month): PunctualityPageDto
    {
        $months = $month->trailing(self::MONTHS);

        return new PunctualityPageDto(
            $months,
            array_map(fn (MonthDto $item): PunctualityReportDto => $this->calculator->calculate($item->range()), $months),
            $this->target,
        );
    }
}

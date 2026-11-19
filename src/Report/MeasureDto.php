<?php

declare(strict_types=1);

namespace App\Report;

/**
 * One punctuality measure of the month of a page, with the change from the previous month and
 * the percent of each month.
 */
final readonly class MeasureDto
{
    /**
     * @param string           $key      the key of the texts, for example "pickup_per_citizen"
     * @param string           $cssClass the colour of the measure in the charts
     * @param bool             $dashed   true for the measures for each route run
     * @param ?float           $change   the change from the previous month in percentage points, or null if a month has no value
     * @param list<float|null> $history  the percent of each month, the oldest first
     */
    public function __construct(
        public string $key,
        public string $cssClass,
        public bool $dashed,
        public PunctualityCountDto $count,
        public ?float $change,
        public array $history,
    ) {
    }

    public function percent(): ?float
    {
        return $this->count->percent();
    }
}

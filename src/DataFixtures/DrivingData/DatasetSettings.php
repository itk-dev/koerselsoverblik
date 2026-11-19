<?php

declare(strict_types=1);

namespace App\DataFixtures\DrivingData;

/**
 * The settings of one run of the development dataset. The punctuality is a slider, so that the
 * dataset never holds the level of the real data: a start value for the first month, and a
 * change for each month after it.
 */
final readonly class DatasetSettings
{
    /**
     * @param int                $seed             the same seed gives the same rows
     * @param \DateTimeImmutable $firstMonth       a day in the first month of the dataset
     * @param float              $punctualityStart the share in percent of the runs with an on-time last drop-off in the first month
     * @param float              $punctualityTrend the change in percentage points for each month after the first month
     */
    public function __construct(
        public int $seed,
        public \DateTimeImmutable $firstMonth,
        public float $punctualityStart,
        public float $punctualityTrend,
    ) {
    }

    /**
     * Gives the share in percent of the runs with an on-time last drop-off on a day, from 0 to 100.
     */
    public function punctuality(\DateTimeImmutable $day): float
    {
        $months = ((int) $day->format('Y') - (int) $this->firstMonth->format('Y')) * 12
            + (int) $day->format('n') - (int) $this->firstMonth->format('n');

        return min(100.0, max(0.0, $this->punctualityStart + $this->punctualityTrend * max(0, $months)));
    }
}

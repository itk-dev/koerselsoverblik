<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The real solo runs of a date range: the runs with at most one citizen in the car at a time.
 */
final readonly class SoloCountDto
{
    /**
     * @param int $runs     the runs that are not cancelled and have a passenger count
     * @param int $soloRuns the runs of them with a peak of at most 1 passenger
     */
    public function __construct(
        public int $runs,
        public int $soloRuns,
    ) {
    }

    /**
     * Gives the share of the real solo runs in percent, or null if there are no runs.
     */
    public function percent(): ?float
    {
        return 0 === $this->runs ? null : $this->soloRuns / $this->runs * 100;
    }
}

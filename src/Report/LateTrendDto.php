<?php

declare(strict_types=1);

namespace App\Report;

/**
 * A count of late runs in the period of a page, with its share of the measured runs. The change
 * and the history are shares, so that months with different volumes can be compared. A period
 * with no measured runs has no count and no share.
 */
final readonly class LateTrendDto
{
    /**
     * @param ?int             $count   the late runs of the period, or null if the period has no measured runs
     * @param ?float           $share   the late runs in percent of the measured runs, or null if the period has no measured runs
     * @param int              $runs    the measured runs of the period
     * @param ?float           $change  the change of the share in percentage points, or null if one of the two periods has no measured runs
     * @param list<float|null> $history the share of each month, the oldest first
     */
    public function __construct(
        public ?int $count,
        public ?float $share,
        public int $runs,
        public ?float $change,
        public array $history,
    ) {
    }
}

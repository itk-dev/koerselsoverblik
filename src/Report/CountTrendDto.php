<?php

declare(strict_types=1);

namespace App\Report;

/**
 * A count of the month of a page, with the change from the previous month and the count of each
 * month.
 */
final readonly class CountTrendDto
{
    /**
     * @param ?int        $change  the change from the previous month, or null if the previous month has no measured runs
     * @param list<float> $history the count of each month, the oldest first
     */
    public function __construct(
        public int $current,
        public ?int $change,
        public array $history,
    ) {
    }
}

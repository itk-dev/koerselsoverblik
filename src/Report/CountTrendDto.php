<?php

declare(strict_types=1);

namespace App\Report;

/**
 * A count of the month of a page, with the change from the previous month and the count of each
 * month. A month with no data has no count. The page that makes the count sets the rule for no
 * data.
 */
final readonly class CountTrendDto
{
    /**
     * @param ?int             $current the count of the month, or null if the month has no data
     * @param ?int             $change  the change from the previous month, or null if one of the two months has no data
     * @param list<float|null> $history the count of each month, the oldest first
     */
    public function __construct(
        public ?int $current,
        public ?int $change,
        public array $history,
    ) {
    }
}

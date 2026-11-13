<?php

declare(strict_types=1);

namespace App\Report;

/**
 * A share in percent of the month of a page, with the change from the previous month in
 * percentage points and the share of each month. A month with no data has no share.
 */
final readonly class ShareTrendDto
{
    /**
     * @param ?float           $current the share of the month, or null if the month has no data
     * @param ?float           $change  the change in percentage points, or null if one of the two months has no data
     * @param list<float|null> $history the share of each month, the oldest first
     */
    public function __construct(
        public ?float $current,
        public ?float $change,
        public array $history,
    ) {
    }
}

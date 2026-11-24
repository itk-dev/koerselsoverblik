<?php

declare(strict_types=1);

namespace App\Import;

use App\Import\Extract\ExtractSummaryDto;
use App\Import\Extract\RejectionCountDto;
use App\Import\Extract\RejectionReason;

/**
 * What a read of the stored copies of a range through a mapping gives, before it changes data.
 */
final readonly class StoredCopyPreviewDto
{
    /**
     * @param list<string>      $dates                  the dates with stored stop rows or rejected rows, as Y-m-d, sorted
     * @param int               $storedStopRows         the stored stop rows of these dates, which the read replaces
     * @param int               $storedRejectedRows     the stored rejected rows of these dates, which the read replaces
     * @param ExtractSummaryDto $summary                the rows that the read gives
     * @param list<string>      $missingRequiredColumns the columns of the required fields and the row id that no copy has. The read stops before any change
     * @param list<string>      $missingOptionalColumns the columns of the optional fields that no copy has. The fields get no value
     */
    public function __construct(
        public array $dates,
        public int $storedStopRows,
        public int $storedRejectedRows,
        public ExtractSummaryDto $summary,
        public array $missingRequiredColumns,
        public array $missingOptionalColumns,
    ) {
    }

    /**
     * Tells if a copy gives another date through the mapping than the date of its row. The read
     * replaces one date at a time, so it stops at such a row.
     */
    public function hasRowsWithAnotherDate(): bool
    {
        return array_any($this->summary->rejections, static fn (RejectionCountDto $group): bool => RejectionReason::OutsideRange === $group->reason);
    }
}

<?php

declare(strict_types=1);

namespace App\Import;

use App\Import\Extract\ExtractSummaryDto;

/**
 * What a read of the stored copies of a range through a mapping gives, before it changes data.
 */
final readonly class StoredCopyPreviewDto
{
    /**
     * @param list<string>             $dates                  the dates with stored stop rows or rejected rows, as Y-m-d, sorted
     * @param int                      $storedStopRows         the stored stop rows of these dates, which the read replaces
     * @param int                      $storedRejectedRows     the stored rejected rows that the read replaces, also the rows with no date of a batch of one of these dates
     * @param ExtractSummaryDto        $summary                the rows that the read gives
     * @param list<string>             $missingRequiredColumns the columns of the required fields and the row id that no copy has
     * @param list<string>             $missingOptionalColumns the columns of the optional fields that no copy has. The fields get no value
     * @param list<array{string, int}> $rowsWithAnotherDate    the date and the row number of each copy that gives another date than the date of its row
     * @param list<string>             $datesWithNoStopRows    the dates that have stop rows, but whose copies give none
     */
    public function __construct(
        public array $dates,
        public int $storedStopRows,
        public int $storedRejectedRows,
        public ExtractSummaryDto $summary,
        public array $missingRequiredColumns,
        public array $missingOptionalColumns,
        public array $rowsWithAnotherDate = [],
        public array $datesWithNoStopRows = [],
    ) {
    }

    /**
     * Gives the first problem that stops the read before any change, or null if the read can
     * start.
     */
    public function problem(): ?StoredCopyException
    {
        if ([] !== $this->missingRequiredColumns) {
            return StoredCopyException::missingColumns($this->missingRequiredColumns);
        }
        if ([] !== $this->rowsWithAnotherDate) {
            return StoredCopyException::otherDate(...$this->rowsWithAnotherDate[0]);
        }
        if ([] !== $this->datesWithNoStopRows) {
            return StoredCopyException::noStopRows($this->datesWithNoStopRows[0]);
        }

        return null;
    }
}

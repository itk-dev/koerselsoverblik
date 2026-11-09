<?php

namespace App\Import;

use App\Import\Extract\ExtractSummaryDto;

/**
 * What an import of a file would do, before the user confirms it. It has no values of the
 * rejected rows or of the warnings.
 */
final readonly class ImportPreviewDto
{
    /**
     * @param int          $rowsToDelete the stored stop rows in the range, from all mappings
     * @param list<string> $emptyDates   the dates of the range with no stop rows in the file, as Y-m-d
     */
    public function __construct(
        public ExtractSummaryDto $summary,
        public int $rowsToDelete,
        public array $emptyDates,
    ) {
    }
}

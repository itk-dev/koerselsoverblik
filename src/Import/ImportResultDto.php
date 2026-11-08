<?php

namespace App\Import;

use App\Import\Extract\RejectionCountDto;

/**
 * The result of the replacement of the stop rows of a date range. It has no values from the
 * extract.
 */
final readonly class ImportResultDto
{
    /**
     * @param list<RejectionCountDto> $rejections the rejected rows, grouped by field and reason
     * @param list<RejectionCountDto> $warnings   the warnings of the inserted rows, grouped by field and reason
     * @param list<string>            $emptyDates the dates of the range with no stop rows after the import, as Y-m-d
     */
    public function __construct(
        public int $deletedRows,
        public int $insertedRows,
        public int $rejectedRows,
        public array $rejections,
        public int $warningCount,
        public array $warnings,
        public array $emptyDates,
    ) {
    }
}

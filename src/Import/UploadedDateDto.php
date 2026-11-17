<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportBatch;

/**
 * A date with driving data on the upload page: the counts, and the last upload of the date.
 */
final readonly class UploadedDateDto
{
    /**
     * @param ?ImportBatch $lastUpload the newest batch whose range has the date, or null if no batch has it
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public int $stopRows,
        public int $routeRuns,
        public int $rejectedRows,
        public ?ImportBatch $lastUpload,
    ) {
    }
}

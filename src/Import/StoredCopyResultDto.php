<?php

declare(strict_types=1);

namespace App\Import;

/**
 * The counts of a read of the stored copies of a range, for all the dates of the range.
 */
final readonly class StoredCopyResultDto
{
    /**
     * @param int $dates        the dates that the read replaced
     * @param int $stopRows     the stop rows that the read gave
     * @param int $rejectedRows the rejected rows that the read gave
     * @param int $deletedRows  the stop rows that the read replaced
     */
    public function __construct(
        public int $dates,
        public int $stopRows,
        public int $rejectedRows,
        public int $deletedRows,
    ) {
    }
}

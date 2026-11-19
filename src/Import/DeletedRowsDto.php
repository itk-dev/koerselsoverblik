<?php

declare(strict_types=1);

namespace App\Import;

/**
 * The numbers of stop rows and rejected rows that the delete of a range deleted.
 */
final readonly class DeletedRowsDto
{
    public function __construct(
        public int $stopRows,
        public int $rejectedRows,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Import;

/**
 * The numbers of rows that a clear of the driving data deletes.
 */
final readonly class ClearedRowsDto
{
    public function __construct(
        public int $stopRows,
        public int $routeRuns,
        public int $rejectedRows,
    ) {
    }
}

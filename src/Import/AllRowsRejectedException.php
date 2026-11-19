<?php

declare(strict_types=1);

namespace App\Import;

/**
 * The file has data rows, but the parser rejects all of them. The file is broken, not empty.
 */
final class AllRowsRejectedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The file has data rows, but all rows are rejected.');
    }
}

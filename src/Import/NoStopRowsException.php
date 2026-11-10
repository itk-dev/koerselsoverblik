<?php

declare(strict_types=1);

namespace App\Import;

/**
 * The file has no stop rows in the range of the import. The import would only delete the stored
 * rows of the range, so it stops and changes no data. A wrong range is the usual cause.
 */
final class NoStopRowsException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The file has no stop rows in the range. The import changed no data.');
    }
}

<?php

namespace App\Import;

/**
 * A row id of the file is already stored on a date outside the range of the import. The import
 * changed no data.
 *
 * Each extract holds whole days, so this does not occur with unique row ids. The message never has
 * a value from the extract, and the exception keeps no previous exception, because the message of
 * the database has the row id.
 */
final class RowIdConflictException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('A row id of the file is already stored on a date outside the range. The import changed no data.');
    }
}

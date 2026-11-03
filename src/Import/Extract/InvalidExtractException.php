<?php

namespace App\Import\Extract;

/**
 * The parser cannot read an extract, for example because a column is missing. No row of the
 * file is imported.
 *
 * The message can have the names from the mapping. It never has a value from the extract.
 */
final class InvalidExtractException extends \RuntimeException
{
}

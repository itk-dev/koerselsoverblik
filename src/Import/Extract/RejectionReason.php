<?php

declare(strict_types=1);

namespace App\Import\Extract;

/**
 * Why the parser rejects a row of an extract.
 *
 * A reason is a code, not a text with the cell value. The user interface translates it, and the
 * import batch stores it. Logs and messages then never contain a value from the extract.
 */
enum RejectionReason: string
{
    case Empty = 'empty';
    case ErrorValue = 'error_value';
    case NotText = 'not_text';
    case UnknownCode = 'unknown_code';
    case NotWholeNumber = 'not_whole_number';
    case NotNumber = 'not_number';
    case UnknownBoolValue = 'unknown_bool_value';
    case NotDate = 'not_date';
    case NotDateTime = 'not_date_time';
    case NotTime = 'not_time';
    case OutsideRange = 'outside_range';
    // The row id is empty, or it is not text or a whole number.
    case InvalidRowId = 'invalid_row_id';
    // An earlier row of the file has the same row id. The import rejects the later rows.
    case DuplicateRowId = 'duplicate_row_id';
}

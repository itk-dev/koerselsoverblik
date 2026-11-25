<?php

declare(strict_types=1);

namespace App\Import;

/**
 * The read of the stored copies of a date stops, and the date keeps its rows. The dates before it
 * are already read again. The message has no field values.
 */
final class StoredCopyException extends \RuntimeException
{
    /**
     * The read cannot fill a required field or the row id of any row, as a file with a missing
     * column.
     *
     * @param list<string> $columns
     */
    public static function missingColumns(array $columns): self
    {
        return new self(\sprintf('No copy of the range has these columns of the mapping: %s. Nothing is changed.', implode(', ', $columns)));
    }

    /**
     * The read would only delete the stop rows of the date. A wrong mapping is the usual cause.
     */
    public static function noStopRows(string $date): self
    {
        return new self(\sprintf('The copies of %s give no stop rows through the mapping, but the date has stop rows. The date keeps its rows.', $date));
    }

    /**
     * The read replaces one date at a time. A row with another date would be lost, because the
     * replacement does not write a row outside the date.
     */
    public static function otherDate(string $date, int $rowNumber): self
    {
        return new self(\sprintf('The copy of row %d of %s gives another date through the mapping. The date keeps its rows.', $rowNumber, $date));
    }

    /**
     * A copy has a value of a type that the copy cannot have. The name of the column can be in the
     * previous exception, but no value.
     */
    public static function unreadableCopy(string $date, \Throwable $previous): self
    {
        return new self(\sprintf('A copy of %s has a value of an unknown type. The date keeps its rows.', $date), 0, $previous);
    }
}

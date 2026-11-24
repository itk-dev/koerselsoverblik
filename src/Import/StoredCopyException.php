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
     * The read would only delete the stop rows of the date. A wrong mapping is the usual cause.
     */
    public static function noStopRows(\DateTimeImmutable $date): self
    {
        return new self(\sprintf('The copies of %s give no stop rows through the mapping. The date keeps its rows.', $date->format('Y-m-d')));
    }

    /**
     * The read replaces one date at a time. A row with another date would be lost, because the
     * replacement does not write a row outside the date.
     */
    public static function otherDate(\DateTimeImmutable $date, int $rowNumber): self
    {
        return new self(\sprintf('The copy of row %d of %s gives another date through the mapping. The date keeps its rows.', $rowNumber, $date->format('Y-m-d')));
    }
}

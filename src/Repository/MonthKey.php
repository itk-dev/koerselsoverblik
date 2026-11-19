<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Gives the DQL expression of the month of a date as YYYY-MM, the same as MonthDto::query(). The
 * queries for each month group by it.
 *
 * The expression takes the first 7 characters of the date. This works because MariaDB gives a
 * date as YYYY-MM-DD text. Another database can give another text.
 */
final class MonthKey
{
    /**
     * @param string $date the path to a date field, for example "row.serviceDate"
     */
    public static function of(string $date): string
    {
        return \sprintf('SUBSTRING(%s, 1, 7)', $date);
    }
}

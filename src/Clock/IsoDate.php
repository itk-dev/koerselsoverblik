<?php

declare(strict_types=1);

namespace App\Clock;

/**
 * Reads a date as YYYY-MM-DD from a command argument or a route.
 */
final class IsoDate
{
    /**
     * Gives midnight of the date in the PHP default time zone, or null for a value that is not a
     * valid date. A date that does not exist, for example 2026-02-30, is not valid.
     */
    public static function parse(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}

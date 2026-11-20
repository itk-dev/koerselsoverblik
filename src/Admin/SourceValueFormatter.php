<?php

declare(strict_types=1);

namespace App\Admin;

use App\Import\Extract\SourceCells;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Gives the display text of one value in the copy of a row. The text shows the value of the cell
 * in the extract, not the value that the import read from it.
 *
 * @phpstan-import-type SourceValue from SourceCells
 */
final class SourceValueFormatter
{
    /**
     * The format of SourceCells. The text is read with a pattern, not as a date, because a
     * wall-clock time with no time zone can be a time that the PHP default time zone skips.
     */
    private const string DATE_TIME_PATTERN = '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})\.\d+$/';

    /**
     * A duration shows as hours and minutes, for example 25:10. A date and time shows as
     * d-m-Y H:i. The seconds show only when they are not zero. A value that does not agree with
     * its type shows as its text.
     *
     * @param SourceValue $value
     */
    public static function format(string|int|float|bool|array $value): string|TranslatableMessage
    {
        return match (true) {
            \is_bool($value) => new TranslatableMessage($value ? 'common.yes' : 'common.no'),
            \is_array($value) && isset($value['duration']) => self::duration($value['duration']),
            \is_array($value) && isset($value['datetime']) => self::dateTime($value['datetime']),
            \is_array($value) => $value['error'],
            default => (string) $value,
        };
    }

    private static function duration(string $text): string
    {
        try {
            $interval = new \DateInterval($text);
        } catch (\Exception) {
            return $text;
        }
        $hours = $interval->d * 24 + $interval->h;

        return 0 === $interval->s
            ? \sprintf('%d:%02d', $hours, $interval->i)
            : \sprintf('%d:%02d:%02d', $hours, $interval->i, $interval->s);
    }

    private static function dateTime(string $text): string
    {
        if (1 !== preg_match(self::DATE_TIME_PATTERN, $text, $parts)) {
            return $text;
        }
        [, $year, $month, $day, $hour, $minute, $second] = $parts;

        return \sprintf('%s-%s-%s %s:%s', $day, $month, $year, $hour, $minute).('00' === $second ? '' : ':'.$second);
    }
}

<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The length of the period of a page, as in the filter bar of the prototype.
 */
enum PeriodType: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /**
     * Gives the first date of the period that contains the date. A week starts on Monday.
     */
    public function start(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return match ($this) {
            self::Day => $date,
            self::Week => $date->modify('monday this week'),
            self::Month => $date->modify('first day of this month'),
            self::Year => $date->setDate((int) $date->format('Y'), 1, 1),
        };
    }
}

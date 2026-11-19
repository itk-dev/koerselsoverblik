<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The length of the period of a page. The prototype has a day and a year. The application has a
 * quarter and a half year instead, because the data starts on 18 May 2026, and a longer period
 * shows a trend sooner.
 */
enum PeriodType: string
{
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case HalfYear = 'half_year';

    /**
     * Gives the number of months of the period, or null for a week.
     */
    public function months(): ?int
    {
        return match ($this) {
            self::Week => null,
            self::Month => 1,
            self::Quarter => 3,
            self::HalfYear => 6,
        };
    }

    /**
     * Gives the first date of the period that contains the date: the Monday of a week, or the
     * first day of the month, the quarter or the half year.
     */
    public function start(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $months = $this->months();
        if (null === $months) {
            return $date->modify('monday this week');
        }
        $month = intdiv((int) $date->format('n') - 1, $months) * $months + 1;

        return $date->setDate((int) $date->format('Y'), $month, 1);
    }
}

<?php

declare(strict_types=1);

namespace App\Report;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Gives the date ranges of a filter, as the prototype does:
 *
 * - The period ends today at the latest, and starts at the start of the data at the earliest
 *   (APP_DATA_START_DATE). A period that is cut at one of these dates is partial.
 * - The comparison is the previous period or the same period last year. If the selected period
 *   has not ended, the comparison is cut to the same number of days, so that a month that has
 *   not ended is not compared with a whole month. A comparison period before the start of the
 *   data has no range.
 * - The trend has the 12 months up to the month of the period.
 *
 * All dates are calendar dates at 00:00 in the PHP default time zone (ADR 005).
 */
final readonly class PeriodRangeFactory
{
    public const int MONTHS = 12;

    private ?\DateTimeImmutable $dataStart;

    /**
     * @param string $dataStart the first date of the data as YYYY-MM-DD, or empty for no limit. The value of APP_DATA_START_DATE
     */
    public function __construct(
        #[Autowire(env: 'APP_DATA_START_DATE')]
        string $dataStart = '',
    ) {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dataStart);
        $this->dataStart = false === $date ? null : $date;
    }

    public function create(PeriodFilter $filter, \DateTimeImmutable $today): PeriodSelectionDto
    {
        $today = new \DateTimeImmutable($today->format('Y-m-d'));
        // A date before the start of the data gives the start, so that the period has data.
        if (null !== $this->dataStart) {
            $filter = $filter->with(anchor: min(max($filter->anchor, $this->dataStart), $today));
        }
        $selected = $this->period($filter->type, $filter->anchor, $today);
        // The range is empty only when the start of the data is after today.
        $selectedRange = $selected->range ?? DateRangeDto::create($filter->anchor, $filter->anchor);
        $comparison = $this->period($filter->type, self::comparisonAnchor($filter), $today);
        $previous = self::shift($filter->type, $filter->anchor, false);
        $next = self::shift($filter->type, $filter->anchor, true);

        return new PeriodSelectionDto(
            $filter,
            $selected,
            $selectedRange,
            $comparison,
            self::cut($comparison, $selected->endsAfterToday, $selectedRange),
            null === $this->period($filter->type, $previous, $today)->range ? null : $previous,
            self::start($filter->type, $next) > $today ? null : $next,
            MonthDto::fromQuery($filter->anchor->format('Y-m'), $today)->trailing(self::MONTHS),
        );
    }

    private function period(PeriodType $type, \DateTimeImmutable $anchor, \DateTimeImmutable $today): PeriodDto
    {
        $first = self::start($type, $anchor);
        $months = $type->months();
        $last = null === $months
            ? $first->modify('+6 days')
            : $first->modify(\sprintf('+%d months', $months - 1))->modify('last day of this month');
        $rangeFirst = null === $this->dataStart ? $first : max($first, $this->dataStart);
        $rangeLast = min($last, $today);

        return new PeriodDto(
            $type,
            $first,
            $last,
            $rangeLast < $rangeFirst ? null : DateRangeDto::create($rangeFirst, $rangeLast),
            $last > $today,
            $first < $rangeFirst,
        );
    }

    /**
     * Gives the first date of the period: the Monday of a week, or the first day of the month,
     * the quarter or the half year.
     */
    private static function start(PeriodType $type, \DateTimeImmutable $anchor): \DateTimeImmutable
    {
        $months = $type->months();
        if (null === $months) {
            return $anchor->modify('monday this week');
        }
        $month = intdiv((int) $anchor->format('n') - 1, $months) * $months + 1;

        return $anchor->setDate((int) $anchor->format('Y'), $month, 1);
    }

    /**
     * Gives a date in the next period, or in the previous period.
     */
    private static function shift(PeriodType $type, \DateTimeImmutable $anchor, bool $forward): \DateTimeImmutable
    {
        $months = $type->months();
        $sign = $forward ? '+' : '-';

        return null === $months
            ? $anchor->modify($sign.'7 days')
            : self::start($type, $anchor)->modify($sign.$months.' months');
    }

    /**
     * Gives a date in the comparison period. The same day last year of 29 February is 28 February.
     */
    private static function comparisonAnchor(PeriodFilter $filter): \DateTimeImmutable
    {
        if (Comparison::Previous === $filter->comparison) {
            return self::shift($filter->type, $filter->anchor, false);
        }
        $year = (int) $filter->anchor->format('Y') - 1;
        $month = (int) $filter->anchor->format('n');
        $daysInMonth = (int) $filter->anchor->setDate($year, $month, 1)->format('t');

        return $filter->anchor->setDate($year, $month, min((int) $filter->anchor->format('j'), $daysInMonth));
    }

    /**
     * Cuts the comparison to the number of days of a selected period that has not ended.
     */
    private static function cut(PeriodDto $comparison, bool $selectedEndsAfterToday, DateRangeDto $selectedRange): ?DateRangeDto
    {
        if (null === $comparison->range || !$selectedEndsAfterToday) {
            return $comparison->range;
        }
        $days = (int) $selectedRange->firstDate->diff($selectedRange->lastDate)->days;

        return DateRangeDto::create(
            $comparison->range->firstDate,
            min($comparison->range->firstDate->modify(\sprintf('+%d days', $days)), $comparison->range->lastDate),
        );
    }
}

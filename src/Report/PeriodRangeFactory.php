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
 * - The trend has the 12 months up to the month of the last date of the selected range.
 *
 * All the ranges come from the first date of the period, so each date in a period gives the same
 * result. All dates are calendar dates at 00:00 in the PHP default time zone (ADR 005).
 */
final readonly class PeriodRangeFactory
{
    public const int MONTHS = 12;

    private ?\DateTimeImmutable $dataStart;

    /**
     * @param string $dataStart the first date of the data as YYYY-MM-DD, or empty for no limit. The value of APP_DATA_START_DATE
     *
     * @throws \InvalidArgumentException if the value is not empty and is not a date as YYYY-MM-DD
     */
    public function __construct(
        #[Autowire(env: 'APP_DATA_START_DATE')]
        string $dataStart = '',
    ) {
        if ('' === $dataStart) {
            $this->dataStart = null;

            return;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dataStart);
        // The parser moves a date that does not exist, for example 31 February, to the next month.
        if (false === $date || $date->format('Y-m-d') !== $dataStart) {
            throw new \InvalidArgumentException('APP_DATA_START_DATE must be a date as YYYY-MM-DD.');
        }
        $this->dataStart = $date;
    }

    public function create(PeriodFilter $filter): PeriodSelectionDto
    {
        $today = $filter->today;
        // A date before the start of the data gives the start, so that the period has data.
        if (null !== $this->dataStart) {
            $filter = $filter->with(anchor: min(max($filter->anchor, $this->dataStart), $today));
        }
        $type = $filter->type;
        $first = $type->start($filter->anchor);
        $selected = $this->period($type, $first, $today);
        // The range is empty only when the start of the data is after today.
        $selectedRange = $selected->range ?? DateRangeDto::create($filter->anchor, $filter->anchor);
        $comparison = $this->period($type, self::comparisonStart($type, $first, $filter->comparison), $today);
        $previous = self::shift($type, $first, -1);
        $next = self::shift($type, $first, 1);

        return new PeriodSelectionDto(
            $filter,
            $selected,
            $selectedRange,
            $comparison,
            self::cut($comparison, $selected, $selectedRange),
            null === $this->period($type, $previous, $today)->range ? null : $previous,
            $next > $today ? null : $next,
            MonthDto::of($selectedRange->lastDate)->trailing(self::MONTHS),
        );
    }

    private function period(PeriodType $type, \DateTimeImmutable $first, \DateTimeImmutable $today): PeriodDto
    {
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
     * Gives the first date of the period that is a number of periods before or after a period.
     */
    private static function shift(PeriodType $type, \DateTimeImmutable $first, int $periods): \DateTimeImmutable
    {
        $months = $type->months();

        return null === $months
            ? $first->modify(\sprintf('%+d weeks', $periods))
            : $first->modify(\sprintf('%+d months', $periods * $months));
    }

    /**
     * Gives the first date of the comparison period. The same week last year has the same ISO
     * week number. Week 53 gives week 52 in a year that has 52 weeks.
     */
    private static function comparisonStart(PeriodType $type, \DateTimeImmutable $first, Comparison $comparison): \DateTimeImmutable
    {
        if (Comparison::Previous === $comparison) {
            return self::shift($type, $first, -1);
        }
        if (PeriodType::Week === $type) {
            $year = (int) $first->format('o') - 1;
            // 28 December is always in the last ISO week of its year.
            $weeks = (int) $first->setDate($year, 12, 28)->format('W');

            return $first->setISODate($year, min((int) $first->format('W'), $weeks));
        }

        // The other periods start on the first day of a month, so one year back is exact.
        return $first->modify('-1 year');
    }

    /**
     * Cuts the comparison to the number of days of a selected period that has not ended. The days
     * count from the first date of each period, so that 1–10 June compares with 1–10 May, also when
     * the data starts in May. The comparison has no range if the cut ends before the data.
     */
    private static function cut(PeriodDto $comparison, PeriodDto $selected, DateRangeDto $selectedRange): ?DateRangeDto
    {
        if (null === $comparison->range || !$selected->endsAfterToday) {
            return $comparison->range;
        }
        $days = (int) $selected->firstDate->diff($selectedRange->lastDate)->days;
        $last = min($comparison->firstDate->modify(\sprintf('+%d days', $days)), $comparison->range->lastDate);

        return $last < $comparison->range->firstDate ? null : DateRangeDto::create($comparison->range->firstDate, $last);
    }
}

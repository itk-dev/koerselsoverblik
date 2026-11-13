<?php

declare(strict_types=1);

namespace App\Report;

/**
 * One period of a type: a day, an ISO week, a month or a year.
 */
final readonly class PeriodDto
{
    /**
     * @param \DateTimeImmutable $firstDate the first date of the whole period
     * @param \DateTimeImmutable $lastDate  the last date of the whole period
     * @param DateRangeDto       $range     the dates of the period up to today
     * @param bool               $partial   true when the period has not ended. A day is never partial
     */
    public function __construct(
        public PeriodType $type,
        public \DateTimeImmutable $firstDate,
        public \DateTimeImmutable $lastDate,
        public DateRangeDto $range,
        public bool $partial,
    ) {
    }
}

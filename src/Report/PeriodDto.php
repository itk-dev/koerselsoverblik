<?php

declare(strict_types=1);

namespace App\Report;

/**
 * One period of a type: an ISO week, a month, a quarter or a half year.
 */
final readonly class PeriodDto
{
    /**
     * @param \DateTimeImmutable $firstDate        the first date of the whole period
     * @param \DateTimeImmutable $lastDate         the last date of the whole period
     * @param ?DateRangeDto      $range            the dates of the period from the start of the data up to today, or null if the period has no such dates
     * @param bool               $endsAfterToday   true when the period has not ended
     * @param bool               $startsBeforeData true when the period starts before the start of the data
     */
    public function __construct(
        public PeriodType $type,
        public \DateTimeImmutable $firstDate,
        public \DateTimeImmutable $lastDate,
        public ?DateRangeDto $range,
        public bool $endsAfterToday,
        public bool $startsBeforeData,
    ) {
    }

    /**
     * Tells if the range has fewer dates than the period.
     */
    public function isPartial(): bool
    {
        return $this->endsAfterToday || $this->startsBeforeData;
    }
}

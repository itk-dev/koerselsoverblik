<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The first and the last date of a report. The dates are calendar dates at 00:00 in the PHP
 * default time zone (ADR 005). A report has no upper limit, so it does not use ImportRangeDto.
 */
final readonly class DateRangeDto
{
    private function __construct(
        public \DateTimeImmutable $firstDate,
        public \DateTimeImmutable $lastDate,
    ) {
    }

    /**
     * @throws \InvalidArgumentException if the last date is before the first date
     */
    public static function create(\DateTimeInterface $firstDate, \DateTimeInterface $lastDate): self
    {
        $range = new self(self::day($firstDate), self::day($lastDate));
        if ($range->lastDate < $range->firstDate) {
            throw new \InvalidArgumentException('The last date is before the first date.');
        }

        return $range;
    }

    public function equals(self $other): bool
    {
        return $this->firstDate == $other->firstDate && $this->lastDate == $other->lastDate;
    }

    private static function day(\DateTimeInterface $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date->format('Y-m-d'));
    }
}

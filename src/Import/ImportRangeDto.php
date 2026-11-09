<?php

namespace App\Import;

/**
 * The first and the last date of an upload. The import replaces the stop rows of these dates.
 *
 * The dates are calendar dates at 00:00 in the PHP default time zone. See "Date range" in
 * docs/architecture.md.
 */
final readonly class ImportRangeDto
{
    private function __construct(
        public \DateTimeImmutable $firstDate,
        public \DateTimeImmutable $lastDate,
    ) {
    }

    /**
     * @param int $maxDays the value of APP_DRIVING_DATA_MAX_DAYS
     *
     * @throws InvalidImportRangeException if the last date is before the first date, or the range has more than $maxDays days
     */
    public static function create(\DateTimeInterface $firstDate, \DateTimeInterface $lastDate, int $maxDays): self
    {
        $range = new self(self::day($firstDate), self::day($lastDate));

        if ($range->lastDate < $range->firstDate) {
            throw new InvalidImportRangeException('The last date is before the first date.');
        }
        if ($range->days() > $maxDays) {
            throw new InvalidImportRangeException(\sprintf('The range has more than %d days.', $maxDays));
        }

        return $range;
    }

    public function days(): int
    {
        return $this->firstDate->diff($this->lastDate)->days + 1;
    }

    /**
     * Gives each date of the range, as Y-m-d.
     *
     * @return list<string>
     */
    public function dates(): array
    {
        $dates = [];
        for ($date = $this->firstDate; $date <= $this->lastDate; $date = $date->modify('+1 day')) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * Tells if the date of a date and time is in the range.
     */
    public function contains(\DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');

        return $day >= $this->firstDate->format('Y-m-d') && $day <= $this->lastDate->format('Y-m-d');
    }

    private static function day(\DateTimeInterface $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date->format('Y-m-d'));
    }
}

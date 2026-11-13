<?php

declare(strict_types=1);

namespace App\Report;

/**
 * One calendar month of a page. The first date is at 00:00 in the PHP default time zone.
 */
final readonly class MonthDto
{
    private const string FORMAT = 'Y-m';

    private function __construct(
        private \DateTimeImmutable $firstDate,
    ) {
    }

    /**
     * Reads a month as YYYY-MM. No value, an invalid value and a month after the current month
     * give the current month, so that a link always works.
     */
    public static function fromQuery(?string $value, \DateTimeImmutable $today): self
    {
        $current = self::of($today);
        // The value gets the first day, so that the day of the system clock cannot move the date
        // to the next month. The time of the date is not used.
        $date = null === $value ? false : \DateTimeImmutable::createFromFormat('Y-m-d', $value.'-01');
        if (false === $date || $date->format(self::FORMAT) !== $value) {
            return $current;
        }

        $month = self::of($date);

        return $current->isBefore($month) ? $current : $month;
    }

    /**
     * Gives the month that contains the date.
     */
    public static function of(\DateTimeImmutable $date): self
    {
        return new self($date->modify('first day of this month')->setTime(0, 0));
    }

    public function query(): string
    {
        return $this->firstDate->format(self::FORMAT);
    }

    /**
     * @return int<1, 12>
     */
    public function number(): int
    {
        /** @var int<1, 12> $number */
        $number = (int) $this->firstDate->format('n');

        return $number;
    }

    public function year(): int
    {
        return (int) $this->firstDate->format('Y');
    }

    public function range(): DateRangeDto
    {
        return DateRangeDto::create($this->firstDate, $this->firstDate->modify('last day of this month'));
    }

    public function previous(): self
    {
        return new self($this->firstDate->modify('-1 month'));
    }

    public function next(): self
    {
        return new self($this->firstDate->modify('+1 month'));
    }

    public function isBefore(self $other): bool
    {
        return $this->firstDate < $other->firstDate;
    }

    /**
     * Gives the months up to and including this month, the oldest first.
     *
     * @param positive-int $count
     *
     * @return non-empty-list<self>
     */
    public function trailing(int $count): array
    {
        $months = [];
        for ($index = $count - 1; $index >= 0; --$index) {
            $months[] = new self($this->firstDate->modify(\sprintf('-%d months', $index)));
        }

        return $months;
    }
}

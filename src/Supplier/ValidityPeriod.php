<?php

namespace App\Supplier;

/**
 * The dates of a supplier assignment. Both dates are part of the period. A period without a last
 * date has no end.
 */
final readonly class ValidityPeriod
{
    private string $from;

    private ?string $to;

    public function __construct(\DateTimeInterface $from, ?\DateTimeInterface $to)
    {
        // The dates are compared as Y-m-d text, so the time and the time zone have no effect.
        $this->from = $from->format('Y-m-d');
        $this->to = $to?->format('Y-m-d');
    }

    public function overlaps(self $other): bool
    {
        return self::onOrBefore($this->from, $other->to) && self::onOrBefore($other->from, $this->to);
    }

    public function contains(\DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');

        return $this->from <= $day && self::onOrBefore($day, $this->to);
    }

    /**
     * A null end is later than all dates.
     */
    private static function onOrBefore(string $day, ?string $end): bool
    {
        return null === $end || $day <= $end;
    }
}

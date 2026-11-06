<?php

namespace App\Clock;

use Psr\Clock\ClockInterface;

/**
 * Gives the dates in the PHP default time zone. The clock can give the time in another time zone,
 * so "today" from the clock can be the wrong date shortly after midnight. See ADR 005.
 */
final readonly class LocalCalendar
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    /**
     * Gives midnight at the start of the current date in the PHP default time zone.
     */
    public function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone(date_default_timezone_get()))->setTime(0, 0);
    }
}

<?php

namespace App\Clock;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Gives the dates in the time zone of the application. The PHP default time zone is UTC, so
 * "today" from PHP can be the wrong date shortly after midnight.
 */
final readonly class LocalCalendar
{
    /**
     * @param string $timeZone the value of APP_TIMEZONE
     */
    public function __construct(
        private ClockInterface $clock,
        #[Autowire('%app.timezone%')]
        private string $timeZone,
    ) {
    }

    /**
     * Gives the current date in the time zone of the application, as a calendar date at 00:00 UTC.
     */
    public function today(): \DateTimeImmutable
    {
        $date = $this->clock->now()->setTimezone(new \DateTimeZone($this->timeZone))->format('Y-m-d');

        return new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
    }
}

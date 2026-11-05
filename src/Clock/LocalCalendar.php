<?php

namespace App\Clock;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Gives the dates in the time zone of the application. The PHP default time zone can be UTC in
 * the container, so "today" from PHP can be the wrong date shortly after midnight.
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
     * Gives midnight at the start of the current date in the time zone of the application.
     */
    public function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone($this->timeZone))->setTime(0, 0);
    }
}

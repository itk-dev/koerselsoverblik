<?php

declare(strict_types=1);

namespace App\Report;

/**
 * The tone of a punctuality measure on the page.
 */
enum PunctualityTone: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Danger = 'danger';

    /**
     * Below the target, a measure at or above this percent is a warning. Below it, it is a danger.
     */
    private const float WARNING_LIMIT = 70.0;

    /**
     * Gives the tone of a percent, or null if the measure has no value.
     *
     * @param float $target the value of APP_PUNCTUALITY_TARGET
     */
    public static function fromPercent(?float $percent, float $target): ?self
    {
        return match (true) {
            null === $percent => null,
            $percent >= $target => self::Ok,
            $percent >= self::WARNING_LIMIT => self::Warn,
            default => self::Danger,
        };
    }
}

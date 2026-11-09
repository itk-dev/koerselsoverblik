<?php

declare(strict_types=1);

namespace App\Twig;

use App\Report\PunctualityTone;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Formats the punctuality figures as the prototype does (`js/punctuality.js`). The numbers have a
 * decimal comma. No value shows as "–", not 0 %.
 */
final readonly class PunctualityExtension
{
    private const string NO_VALUE = '–';

    private const string MINUS = '−';

    /**
     * A change smaller than this shows as no change.
     */
    private const float NO_CHANGE = 0.05;

    /**
     * A change of this size or more shows with no decimal.
     */
    private const float LARGE_CHANGE = 10.0;

    /**
     * @param int $target the value of APP_PUNCTUALITY_TARGET
     */
    public function __construct(
        #[Autowire(param: 'app.punctuality_target')]
        private int $target,
    ) {
    }

    /**
     * Gives a whole percent. A value below the target that rounds to the target shows one decimal,
     * truncated, so that a value below the target never shows as the target.
     */
    #[AsTwigFilter('punctuality_percent')]
    public function percent(?float $value): string
    {
        return null === $value ? self::NO_VALUE : self::number($this->shown($value)).' %';
    }

    /**
     * Gives a change in percentage points with its sign: no decimal from 10 points, else one.
     */
    #[AsTwigFilter('percentage_points')]
    public function points(?float $change): string
    {
        if (null === $change) {
            return self::NO_VALUE;
        }
        if (abs($change) < self::NO_CHANGE) {
            return '±0';
        }

        $size = number_format(abs($change), abs($change) >= self::LARGE_CHANGE ? 0 : 1, ',', '.');

        return ($change > 0 ? '+' : self::MINUS).$size;
    }

    /**
     * Gives the distance from the shown value to the target, or null if the measure has no value
     * or reaches the target.
     */
    #[AsTwigFunction('punctuality_gap')]
    public function gap(?float $value): ?string
    {
        if (null === $value || $value >= $this->target) {
            return null;
        }

        return self::number(round(($this->target - $this->shown($value)) * 10) / 10);
    }

    /**
     * Gives the tone of a measure: "ok", "warn" or "danger", or null if it has no value.
     */
    #[AsTwigFunction('punctuality_tone')]
    public function tone(?float $value): ?string
    {
        return PunctualityTone::fromPercent($value, $this->target)?->value;
    }

    /**
     * Tells if a change is good ("up"), bad ("down"), no change ("neutral") or not known ("none").
     *
     * @param bool $higherIsBetter false for a count of late runs
     */
    #[AsTwigFunction('trend_tone')]
    public function trendTone(?float $change, bool $higherIsBetter = true): string
    {
        return match (true) {
            null === $change => 'none',
            abs($change) < self::NO_CHANGE => 'neutral',
            ($change > 0) === $higherIsBetter => 'up',
            default => 'down',
        };
    }

    /**
     * Gives the arrow of the direction of a change, or null if the change is not known.
     */
    #[AsTwigFunction('trend_icon')]
    public function trendIcon(?float $change): ?string
    {
        return match (true) {
            null === $change => null,
            abs($change) < self::NO_CHANGE => 'lucide:minus',
            $change > 0 => 'lucide:arrow-up-right',
            default => 'lucide:arrow-down-right',
        };
    }

    /**
     * Gives a change of a count with its sign.
     */
    #[AsTwigFilter('signed_number')]
    public function signedNumber(?int $change): string
    {
        return match (true) {
            null === $change => self::NO_VALUE,
            0 === $change => '±0',
            default => ($change > 0 ? '+' : self::MINUS).number_format(abs($change), 0, ',', '.'),
        };
    }

    private function shown(float $value): float
    {
        $whole = round($value);

        return $value < $this->target && $whole >= $this->target ? floor($value * 10) / 10 : $whole;
    }

    /**
     * Gives a whole number with no decimal, and other numbers with one decimal.
     */
    private static function number(float $value): string
    {
        return number_format($value, floor($value) === $value ? 0 : 1, ',', '.');
    }
}

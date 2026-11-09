<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * One bar of a column chart, with its position. The chart shows the label, the name of the series
 * and the value as a tooltip.
 */
final readonly class BarDto
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $cssClass,
        public string $name,
        public string $label,
        public float $value,
    ) {
    }
}

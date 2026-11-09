<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * One value of a line, with its position. The chart shows the label and the value as a tooltip.
 */
final readonly class PointDto
{
    public function __construct(
        public float $x,
        public float $y,
        public string $label,
        public float $value,
    ) {
    }
}

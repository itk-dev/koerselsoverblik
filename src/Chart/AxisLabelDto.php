<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * One label on the horizontal axis, for example a month.
 */
final readonly class AxisLabelDto
{
    public function __construct(
        public string $label,
        public float $x,
    ) {
    }
}

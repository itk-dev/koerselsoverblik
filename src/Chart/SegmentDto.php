<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * One part of a distribution bar. The share and the position are in percent of the bar.
 */
final readonly class SegmentDto
{
    public function __construct(
        public string $label,
        public string $cssClass,
        public float $value,
        public float $share,
        public float $x,
    ) {
    }
}

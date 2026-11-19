<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * One horizontal grid line with its value on the vertical axis.
 */
final readonly class GridLineDto
{
    public function __construct(
        public float $value,
        public float $y,
    ) {
    }
}

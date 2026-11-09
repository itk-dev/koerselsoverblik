<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * One line of a line chart: the SVG path and the points with a value.
 */
final readonly class LineDto
{
    /**
     * @param string         $path   the "d" attribute of the SVG path. No value breaks the line.
     * @param list<PointDto> $points
     */
    public function __construct(
        public string $name,
        public string $cssClass,
        public bool $dashed,
        public string $path,
        public array $points,
    ) {
    }
}

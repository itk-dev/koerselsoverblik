<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * One series of a chart: a name, the CSS class of its colour and one value for each label.
 */
final readonly class ChartSeriesDto
{
    /**
     * @param string           $cssClass the class that gives the colour, for example "series-info". A style attribute is not allowed by the Content Security Policy.
     * @param list<float|null> $values   one value for each label, or null for no value
     * @param bool             $dashed   true for a dashed line
     */
    public function __construct(
        public string $name,
        public string $cssClass,
        public array $values,
        public bool $dashed = false,
    ) {
    }
}

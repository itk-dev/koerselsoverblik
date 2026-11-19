<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Chart\LineChartGeometry;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A line chart of some months, with an optional target line and a hidden data table for screen
 * readers. The template only draws the geometry.
 *
 * @phpstan-import-type SeriesArray from ChartSeries
 */
#[AsTwigComponent]
final class LineChart
{
    /**
     * The id of the figure. The caption has the id "{id}-caption".
     */
    public string $id = '';

    public string $caption = '';

    /**
     * @var list<string>
     */
    public array $labels = [];

    /**
     * @var list<SeriesArray>
     */
    public array $series = [];

    /**
     * The top of the scale, or null for a nice maximum above the values.
     */
    public ?float $max = null;

    public ?float $target = null;

    public ?string $targetLabel = null;

    /**
     * "percent" or "count". It gives the format of the values.
     */
    public string $unit = 'percent';

    public function geometry(): LineChartGeometry
    {
        return LineChartGeometry::build($this->labels, ChartSeries::fromArrays($this->series), $this->max, $this->target);
    }
}

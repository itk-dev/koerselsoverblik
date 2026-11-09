<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Chart\ColumnChartGeometry;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A stacked column chart of counts for some months, with a hidden data table for screen readers.
 * The template only draws the geometry.
 *
 * @phpstan-import-type SeriesArray from ChartSeries
 */
#[AsTwigComponent]
final class ColumnChart
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
     * @var list<SeriesArray> the first series is at the bottom of each column
     */
    public array $series = [];

    public function geometry(): ColumnChartGeometry
    {
        return ColumnChartGeometry::build($this->labels, ChartSeries::fromArrays($this->series));
    }
}

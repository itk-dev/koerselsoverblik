<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Chart\ColumnChartGeometry;
use App\Chart\Scale;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A stacked column chart of counts or shares for some months, with a hidden data table for screen
 * readers. The template only draws the geometry.
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

    /**
     * "count" or "share". It gives the format of the values. A share is in percent.
     */
    public string $unit = 'count';

    /**
     * The noun of the counts of a chart of shares, for example "vognløb". The title of a bar
     * shows the share and the count.
     */
    public string $countLabel = '';

    /**
     * The width of the viewBox. Use Scale::HALF_WIDTH for a chart in a panel of half the width.
     */
    public int $width = Scale::WIDTH;

    public function geometry(): ColumnChartGeometry
    {
        return ColumnChartGeometry::build($this->labels, ChartSeries::fromArrays($this->series), $this->width);
    }
}

<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Chart\DistributionGeometry;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A bar that splits a total in parts, with a legend of the values and the shares. Screen readers
 * read the legend, not the bar.
 */
#[AsTwigComponent]
final class DistributionBar
{
    /**
     * The id of the figure. The caption has the id "{id}-caption".
     */
    public string $id = '';

    public string $caption = '';

    /**
     * The total as a text, for example "4 vognløb".
     */
    public string $total = '';

    /**
     * @var list<array{name: string, cssClass: string, value: float|int}>
     */
    public array $segments = [];

    public function geometry(): DistributionGeometry
    {
        return DistributionGeometry::build(ChartSeries::fromArrays(array_map(
            static fn (array $segment): array => ['name' => $segment['name'], 'cssClass' => $segment['cssClass'], 'values' => [$segment['value']]],
            $this->segments,
        )));
    }
}

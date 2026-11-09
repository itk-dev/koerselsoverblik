<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * The positions of a line chart, as in `lineChart()` of the prototype. The template only draws
 * them.
 */
final readonly class LineChartGeometry
{
    /**
     * @param list<GridLineDto>  $gridLines
     * @param list<AxisLabelDto> $xLabels
     * @param list<LineDto>      $lines
     * @param ?float             $targetY   the vertical position of the target line, or null for no target
     */
    private function __construct(
        public array $gridLines,
        public array $xLabels,
        public array $lines,
        public ?float $targetY,
    ) {
    }

    /**
     * @param list<string>         $labels the labels of the horizontal axis
     * @param list<ChartSeriesDto> $series
     * @param ?float               $max    the top of the scale, or null for a nice maximum above the values
     * @param ?float               $target the value of the target line, or null for no target line
     */
    public static function build(array $labels, array $series, ?float $max = null, ?float $target = null): self
    {
        $values = array_filter(array_merge(...array_map(static fn (ChartSeriesDto $item): array => $item->values, $series)), is_float(...));
        $top = $max ?? Scale::niceMax(([] === $values ? 0.0 : max($values)) * Scale::HEADROOM);
        $step = \count($labels) > 1 ? Scale::plotWidth() / (\count($labels) - 1) : 0;

        $xLabels = [];
        foreach ($labels as $index => $label) {
            $xLabels[] = new AxisLabelDto($label, Scale::LEFT + $step * $index);
        }

        $lines = [];
        foreach ($series as $item) {
            $points = [];
            $path = [];
            $command = 'M';
            foreach ($item->values as $index => $value) {
                if (null === $value) {
                    $command = 'M';
                    continue;
                }
                $point = new PointDto($xLabels[$index]->x, Scale::y($value, $top), $labels[$index], $value);
                $points[] = $point;
                $path[] = \sprintf('%s%.1f,%.1f', $command, $point->x, $point->y);
                $command = 'L';
            }
            $lines[] = new LineDto($item->name, $item->cssClass, $item->dashed, implode(' ', $path), $points);
        }

        return new self(Scale::gridLines($top), $xLabels, $lines, null === $target ? null : Scale::y($target, $top));
    }
}

<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * The positions of a stacked column chart, as in `columnChart({stacked: true})` of the
 * prototype. The template only draws them.
 */
final readonly class ColumnChartGeometry
{
    /**
     * The part of the space of a label that the column fills.
     */
    private const float COLUMN_SHARE = 0.62;

    /**
     * @param list<GridLineDto>  $gridLines
     * @param list<AxisLabelDto> $xLabels
     * @param list<BarDto>       $bars
     */
    private function __construct(
        public array $gridLines,
        public array $xLabels,
        public array $bars,
    ) {
    }

    /**
     * Stacks the series in their order, the first at the bottom. A value of 0 has no bar.
     *
     * @param list<string>         $labels the labels of the horizontal axis
     * @param list<ChartSeriesDto> $series
     */
    public static function build(array $labels, array $series): self
    {
        $totals = [];
        foreach ($labels as $index => $label) {
            $totals[] = array_sum(array_map(static fn (ChartSeriesDto $item): float => $item->values[$index] ?? 0.0, $series));
        }
        $top = Scale::niceMax(max([1.0, ...$totals]) * Scale::HEADROOM);
        $slot = Scale::plotWidth() / max(1, \count($labels));
        $width = $slot * self::COLUMN_SHARE;

        $xLabels = [];
        $bars = [];
        foreach ($labels as $index => $label) {
            $left = Scale::LEFT + $slot * $index;
            $xLabels[] = new AxisLabelDto($label, $left + $slot / 2);
            $stacked = 0.0;
            foreach ($series as $item) {
                $value = $item->values[$index] ?? 0.0;
                if ($value <= 0) {
                    continue;
                }
                $height = Scale::BOTTOM - Scale::y($value, $top);
                $stacked += $height;
                $bars[] = new BarDto($left + ($slot - $width) / 2, Scale::BOTTOM - $stacked, $width, $height, $item->cssClass, $item->name, $label, $value);
            }
        }

        return new self(Scale::gridLines($top), $xLabels, $bars);
    }
}

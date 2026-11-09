<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * The parts of a distribution bar, as in `distributionBar()` of the prototype. The prototype sets
 * the width of each part in a style attribute. The Content Security Policy does not allow that,
 * so the template draws the parts as SVG rectangles.
 */
final readonly class DistributionGeometry
{
    /**
     * @param list<SegmentDto> $segments one for each series, also with no share
     */
    private function __construct(
        public float $total,
        public array $segments,
    ) {
    }

    /**
     * @param list<ChartSeriesDto> $series one value for each series
     */
    public static function build(array $series): self
    {
        $values = array_map(static fn (ChartSeriesDto $item): float => $item->values[0] ?? 0.0, $series);
        $total = array_sum($values);

        $segments = [];
        $x = 0.0;
        foreach ($series as $index => $item) {
            $share = $total > 0 ? $values[$index] / $total * 100 : 0.0;
            $segments[] = new SegmentDto($item->name, $item->cssClass, $values[$index], $share, $x);
            $x += $share;
        }

        return new self($total, $segments);
    }

    /**
     * Gives the parts with a share. A part with no share is only in the legend.
     *
     * @return list<SegmentDto>
     */
    public function barSegments(): array
    {
        return array_values(array_filter($this->segments, static fn (SegmentDto $segment): bool => $segment->share > 0));
    }
}

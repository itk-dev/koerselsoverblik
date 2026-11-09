<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chart;

use App\Chart\AxisLabelDto;
use App\Chart\ChartSeriesDto;
use App\Chart\ColumnChartGeometry;
use App\Chart\DistributionGeometry;
use App\Chart\GridLineDto;
use App\Chart\LineChartGeometry;
use App\Chart\Scale;
use App\Chart\SparklineGeometry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the geometry of the charts, with the sizes of the prototype (`js/chart.js`): a view box
 * of 840 × 280, and a plot area from x 62 to 824 and from y 16 to 244.
 */
final class ChartGeometryTest extends TestCase
{
    #[DataProvider('maxima')]
    public function testNiceMaximum(float $value, float $expected): void
    {
        self::assertSame($expected, Scale::niceMax($value));
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function maxima(): iterable
    {
        yield 'zero' => [0.0, 1.0];
        yield 'negative' => [-5.0, 1.0];
        yield 'below one' => [0.7, 1.0];
        yield 'one' => [1.0, 1.0];
        yield 'up to two' => [1.5, 2.0];
        yield 'two' => [2.0, 2.0];
        yield 'up to two and a half' => [2.2, 2.5];
        yield 'up to five' => [3.0, 5.0];
        yield 'five' => [5.0, 5.0];
        yield 'up to ten' => [7.0, 10.0];
        yield 'twenty five' => [25.0, 25.0];
        yield 'two hundred' => [108.0, 200.0];
    }

    public function testLineChart(): void
    {
        $geometry = LineChartGeometry::build(['jan', 'feb', 'mar'], [
            new ChartSeriesDto('Afhentning', 'series-info', [0.0, 50.0, 100.0]),
            new ChartSeriesDto('Afsætning', 'series-blue', [10.0, null, 30.0], dashed: true),
        ], max: 100.0, target: 90.0);

        self::assertEquals([
            new GridLineDto(0.0, 244.0),
            new GridLineDto(25.0, 187.0),
            new GridLineDto(50.0, 130.0),
            new GridLineDto(75.0, 73.0),
            new GridLineDto(100.0, 16.0),
        ], $geometry->gridLines);
        self::assertEquals([new AxisLabelDto('jan', 62.0), new AxisLabelDto('feb', 443.0), new AxisLabelDto('mar', 824.0)], $geometry->xLabels);
        self::assertEqualsWithDelta(38.8, $geometry->targetY, 0.001);

        [$first, $second] = $geometry->lines;
        self::assertSame('M62.0,244.0 L443.0,130.0 L824.0,16.0', $first->path);
        self::assertSame('series-info', $first->cssClass);
        self::assertFalse($first->dashed);
        self::assertCount(3, $first->points);
        self::assertSame('feb', $first->points[1]->label);
        self::assertSame(50.0, $first->points[1]->value);
        self::assertSame(443.0, $first->points[1]->x);
        self::assertSame(130.0, $first->points[1]->y);
        // No value breaks the line, and has no point.
        self::assertSame('M62.0,221.2 M824.0,175.6', $second->path);
        self::assertTrue($second->dashed);
        self::assertCount(2, $second->points);
    }

    /**
     * With no maximum, the scale is the nice maximum above the highest value plus 8 %.
     */
    public function testLineChartScaleFromTheValues(): void
    {
        $geometry = LineChartGeometry::build(['a', 'b'], [new ChartSeriesDto('S', 'series-info', [10.0, 20.0])]);

        self::assertSame(25.0, $geometry->gridLines[4]->value);
        self::assertNull($geometry->targetY);
        self::assertEquals([new AxisLabelDto('a', 62.0), new AxisLabelDto('b', 824.0)], $geometry->xLabels);
    }

    /**
     * One label is at the left of the plot area.
     */
    public function testLineChartWithOneLabel(): void
    {
        $geometry = LineChartGeometry::build(['a'], [new ChartSeriesDto('S', 'series-info', [10.0])]);

        self::assertEquals([new AxisLabelDto('a', 62.0)], $geometry->xLabels);
        // 10 plus 8 % gives a scale of 20, so 10 is in the middle.
        self::assertSame('M62.0,130.0', $geometry->lines[0]->path);
    }

    public function testLineChartWithNoValues(): void
    {
        $geometry = LineChartGeometry::build(['a', 'b'], [new ChartSeriesDto('S', 'series-info', [null, null])]);

        self::assertSame(1.0, $geometry->gridLines[4]->value);
        self::assertSame('', $geometry->lines[0]->path);
        self::assertSame([], $geometry->lines[0]->points);
    }

    public function testStackedColumnChart(): void
    {
        $geometry = ColumnChartGeometry::build(['a', 'b'], [
            new ChartSeriesDto('5–15', 'series-warning', [2.0, 0.0]),
            new ChartSeriesDto('15–30', 'series-coral', [1.0, 3.0]),
        ]);

        // The highest total is 3, plus 8 %, and the nice maximum is 5.
        self::assertSame(5.0, $geometry->gridLines[4]->value);
        self::assertEqualsWithDelta(252.5, $geometry->xLabels[0]->x, 0.001);
        self::assertEqualsWithDelta(633.5, $geometry->xLabels[1]->x, 0.001);
        // A value of 0 has no bar.
        self::assertCount(3, $geometry->bars);
        [$first, $second, $third] = $geometry->bars;
        self::assertSame('series-warning', $first->cssClass);
        self::assertEqualsWithDelta(134.39, $first->x, 0.001);
        self::assertEqualsWithDelta(236.22, $first->width, 0.001);
        self::assertEqualsWithDelta(152.8, $first->y, 0.001);
        self::assertEqualsWithDelta(91.2, $first->height, 0.001);
        self::assertSame('a', $first->label);
        self::assertSame('5–15', $first->name);
        self::assertSame(2.0, $first->value);
        // The second series is on top of the first.
        self::assertEqualsWithDelta(107.2, $second->y, 0.001);
        self::assertEqualsWithDelta(45.6, $second->height, 0.001);
        self::assertEqualsWithDelta(515.39, $third->x, 0.001);
        self::assertEqualsWithDelta(107.2, $third->y, 0.001);
        self::assertEqualsWithDelta(136.8, $third->height, 0.001);
    }

    public function testColumnChartWithNoValues(): void
    {
        $geometry = ColumnChartGeometry::build(['a'], [new ChartSeriesDto('S', 'series-warning', [0.0])]);

        self::assertSame(2.0, $geometry->gridLines[4]->value);
        self::assertSame([], $geometry->bars);
        // One label fills the plot area.
        self::assertSame(443.0, $geometry->xLabels[0]->x);
    }

    /**
     * The scale comes from the highest total, also when it is not the first. A label with no value
     * in a series has no bar.
     */
    public function testColumnChartScaleFromTheHighestTotal(): void
    {
        $geometry = ColumnChartGeometry::build(['a', 'b'], [new ChartSeriesDto('S', 'series-warning', [1.0])]);

        self::assertSame(2.0, $geometry->gridLines[4]->value);
        self::assertCount(1, $geometry->bars);

        $geometry = ColumnChartGeometry::build(['a', 'b'], [new ChartSeriesDto('S', 'series-warning', [1.0, 4.0])]);

        self::assertSame(5.0, $geometry->gridLines[4]->value);
    }

    public function testColumnChartWithNoLabels(): void
    {
        $geometry = ColumnChartGeometry::build([], []);

        self::assertSame([], $geometry->xLabels);
        self::assertSame([], $geometry->bars);
    }

    public function testSparkline(): void
    {
        self::assertSame('M0.0,29.0 L60.0,3.0 L120.0,16.0', SparklineGeometry::path([1.0, 3.0, 2.0]));
        // No value breaks the line.
        self::assertSame('M0.0,29.0 M120.0,3.0', SparklineGeometry::path([1.0, null, 3.0]));
        // The same values give a flat line at the bottom.
        self::assertSame('M0.0,29.0 L120.0,29.0', SparklineGeometry::path([5.0, 5.0]));
    }

    /**
     * A sparkline needs two values.
     */
    public function testSparklineWithFewerThanTwoValues(): void
    {
        self::assertNull(SparklineGeometry::path([5.0]));
        self::assertNull(SparklineGeometry::path([null, 5.0, null]));
    }

    public function testDistribution(): void
    {
        $geometry = DistributionGeometry::build([
            new ChartSeriesDto('Til tiden', 'series-success', [3.0]),
            new ChartSeriesDto('5–15', 'series-warning', [1.0]),
            new ChartSeriesDto('Over 30', 'series-danger', [0.0]),
        ]);

        self::assertSame(4.0, $geometry->total);
        self::assertCount(3, $geometry->segments);
        [$first, $second, $third] = $geometry->segments;
        self::assertSame('Til tiden', $first->label);
        self::assertSame('series-success', $first->cssClass);
        self::assertSame(3.0, $first->value);
        self::assertSame(75.0, $first->share);
        self::assertSame(0.0, $first->x);
        self::assertSame(25.0, $second->share);
        self::assertSame(75.0, $second->x);
        self::assertSame(0.0, $third->share);
        self::assertSame(100.0, $third->x);
        // A segment with no share is in the legend, but not in the bar.
        self::assertSame([$first, $second], $geometry->barSegments());
    }

    /**
     * The bar is a list with no gap, also when a part in the middle has no share. A series with no
     * value has no share.
     */
    public function testDistributionWithNoShareInTheMiddle(): void
    {
        $geometry = DistributionGeometry::build([
            new ChartSeriesDto('Til tiden', 'series-success', [3.0]),
            new ChartSeriesDto('5–15', 'series-warning', []),
            new ChartSeriesDto('Over 30', 'series-danger', [1.0]),
        ]);

        self::assertSame(0.0, $geometry->segments[1]->share);
        self::assertSame([$geometry->segments[0], $geometry->segments[2]], $geometry->barSegments());
    }

    public function testDistributionWithNoValues(): void
    {
        $geometry = DistributionGeometry::build([new ChartSeriesDto('Til tiden', 'series-success', [0.0])]);

        self::assertSame(0.0, $geometry->total);
        self::assertSame(0.0, $geometry->segments[0]->share);
        self::assertSame([], $geometry->barSegments());
    }
}

<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * The plot area and the vertical scale of the line chart and the column chart. The sizes are the
 * sizes of the prototype (`js/chart.js`).
 */
final class Scale
{
    public const int WIDTH = 840;

    public const int HEIGHT = 280;

    public const int LEFT = 62;

    public const int RIGHT = 824;

    public const int TOP = 16;

    /**
     * The bottom of the plot area. The labels of the months are below it.
     */
    public const int BOTTOM = 244;

    /**
     * The extra space above the highest value, so that a line does not touch the top.
     */
    public const float HEADROOM = 1.08;

    private const int GRID_STEPS = 4;

    /**
     * Gives the lowest of 1, 2, 2.5, 5 and 10 × a power of 10 that is at least the value. A value
     * of 0 or less gives 1.
     */
    public static function niceMax(float $value): float
    {
        if ($value <= 0) {
            return 1.0;
        }

        $power = 10 ** floor(log10($value));
        $step = $value / $power;

        return match (true) {
            $step <= 1 => 1,
            $step <= 2 => 2,
            $step <= 2.5 => 2.5,
            $step <= 5 => 5,
            default => 10,
        } * $power;
    }

    /**
     * Gives the vertical position of a value on a scale from 0 to the maximum.
     */
    public static function y(float $value, float $max): float
    {
        return self::BOTTOM - $value / $max * (self::BOTTOM - self::TOP);
    }

    /**
     * Gives five grid lines from 0 to the maximum.
     *
     * @return list<GridLineDto>
     */
    public static function gridLines(float $max): array
    {
        $lines = [];
        for ($step = 0; $step <= self::GRID_STEPS; ++$step) {
            $value = $max * $step / self::GRID_STEPS;
            $lines[] = new GridLineDto($value, self::y($value, $max));
        }

        return $lines;
    }

    public static function plotWidth(): int
    {
        return self::RIGHT - self::LEFT;
    }
}

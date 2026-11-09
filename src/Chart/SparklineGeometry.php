<?php

declare(strict_types=1);

namespace App\Chart;

/**
 * The path of a sparkline, as in `sparkline()` of the prototype: a view box of 120 × 32, scaled
 * from the lowest to the highest value.
 */
final class SparklineGeometry
{
    public const int WIDTH = 120;

    public const int HEIGHT = 32;

    private const int MARGIN = 3;

    /**
     * Gives the "d" attribute of the SVG path, or null if the line has fewer than two values. No
     * value breaks the line.
     *
     * @param list<float|null> $values
     */
    public static function path(array $values): ?string
    {
        $known = array_filter($values, is_float(...));
        if (\count($known) < 2) {
            return null;
        }

        $low = min($known);
        $span = max($known) - $low ?: 1.0;
        $step = self::WIDTH / (\count($values) - 1);

        $path = [];
        $command = 'M';
        foreach ($values as $index => $value) {
            if (null === $value) {
                $command = 'M';
                continue;
            }
            $y = self::HEIGHT - self::MARGIN - ($value - $low) / $span * (self::HEIGHT - 2 * self::MARGIN);
            $path[] = \sprintf('%s%.1f,%.1f', $command, $step * $index, $y);
            $command = 'L';
        }

        return implode(' ', $path);
    }
}

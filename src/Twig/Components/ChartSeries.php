<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Chart\ChartSeriesDto;

/**
 * Makes the series of a chart from the arrays of a template. A template cannot create an object,
 * and the names of the series are translated texts.
 *
 * @phpstan-type SeriesArray array{name: string, cssClass: string, values: list<float|int|null>, dashed?: bool}
 */
final class ChartSeries
{
    /**
     * @param list<SeriesArray> $series
     *
     * @return list<ChartSeriesDto>
     */
    public static function fromArrays(array $series): array
    {
        return array_map(static fn (array $item): ChartSeriesDto => new ChartSeriesDto(
            $item['name'],
            $item['cssClass'],
            array_map(static fn (float|int|null $value): ?float => null === $value ? null : (float) $value, $item['values']),
            $item['dashed'] ?? false,
        ), $series);
    }
}

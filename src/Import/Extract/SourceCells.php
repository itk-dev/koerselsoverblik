<?php

namespace App\Import\Extract;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateIntervalCell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;

/**
 * Makes the copy of the cells of a row, and the cells again from a copy.
 *
 * The copy keeps the type of each cell, so that the parser can read a copy in the same way as the
 * row of the file. A JSON value has no type for a duration or a date and time, so these values
 * are objects with one key. See "Keep a copy of each imported row" in docs/architecture.md.
 *
 * @phpstan-type SourceValue string|int|float|bool|array{duration: string}|array{datetime: string}|array{error: string}|array{formula: string}
 */
final class SourceCells
{
    /**
     * The days and the hours are separate, because OpenSpout gives 25:10 as 25 hours and no days.
     */
    private const string DURATION_FORMAT = 'P%dDT%hH%iM%sS';

    /**
     * A wall-clock time with no time zone. The microseconds are kept, so that the copy is exact.
     */
    private const string DATE_TIME_FORMAT = 'Y-m-d H:i:s.u';

    /**
     * Gives the copy of the cells. An empty cell is left out.
     *
     * @param array<string, Cell> $cells keyed by the column name
     *
     * @return array<string, SourceValue>
     */
    public static function copy(array $cells): array
    {
        $copy = [];
        foreach ($cells as $column => $cell) {
            $value = self::value($cell);
            if (null !== $value) {
                $copy[$column] = $value;
            }
        }

        return $copy;
    }

    /**
     * Gives the cells again from a copy.
     *
     * @param array<string, mixed> $values keyed by the column name
     *
     * @return array<string, Cell>
     *
     * @throws \InvalidArgumentException if a value does not have a type of the copy
     */
    public static function cells(array $values): array
    {
        $cells = [];
        foreach ($values as $column => $value) {
            $cells[$column] = self::cell($column, $value);
        }

        return $cells;
    }

    /**
     * @return SourceValue|null
     */
    private static function value(Cell $cell): string|int|float|bool|array|null
    {
        return match (true) {
            $cell instanceof ErrorCell => ['error' => $cell->getRawValue()],
            $cell instanceof FormulaCell => ['formula' => $cell->getValue()],
            $cell instanceof DateIntervalCell => ['duration' => $cell->getValue()->format(self::DURATION_FORMAT)],
            $cell instanceof DateTimeCell => ['datetime' => $cell->getValue()->format(self::DATE_TIME_FORMAT)],
            '' === $cell->getValue() => null,
            default => self::scalar($cell),
        };
    }

    /**
     * The other cells have text, a number or a boolean. An empty cell has no value. The XLSX
     * reader gives no cell with rich text or an image, so the copy leaves them out.
     */
    private static function scalar(Cell $cell): string|int|float|bool|null
    {
        $value = $cell->getValue();

        return \is_string($value) || \is_int($value) || \is_float($value) || \is_bool($value) ? $value : null;
    }

    /**
     * @throws \InvalidArgumentException if the value does not have a type of the copy
     */
    private static function cell(string $column, mixed $value): Cell
    {
        if (\is_string($value) || \is_int($value) || \is_float($value) || \is_bool($value)) {
            return Cell::fromValue($value);
        }

        $cell = \is_array($value) && 1 === \count($value) && \is_string(current($value))
            ? self::typedCell(key($value), current($value))
            : null;

        return $cell ?? throw new \InvalidArgumentException(\sprintf('The copy has a value of an unknown type in the column "%s".', $column));
    }

    /**
     * Gives null if the key is not a type of the copy, or if the text does not agree with it. The
     * exception of PHP has the text, so it is not kept.
     */
    private static function typedCell(int|string|null $type, string $text): ?Cell
    {
        try {
            return match ($type) {
                'duration' => new DateIntervalCell(new \DateInterval($text)),
                'datetime' => self::dateTimeCell($text),
                'error' => new ErrorCell($text),
                'formula' => new FormulaCell($text),
                default => null,
            };
        } catch (\Exception) {
            return null;
        }
    }

    private static function dateTimeCell(string $text): ?DateTimeCell
    {
        $value = \DateTimeImmutable::createFromFormat(self::DATE_TIME_FORMAT, $text);

        return false === $value ? null : new DateTimeCell($value);
    }
}

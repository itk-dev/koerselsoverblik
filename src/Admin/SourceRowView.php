<?php

declare(strict_types=1);

namespace App\Admin;

use App\Import\Extract\SourceCells;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The columns of the copy of a row, in the order of the copy. The values of the person id column and
 * of the hidden columns of the mapping, for example the driver id, never show. The mapping tells
 * which columns these are, so with no mapping the view has no columns.
 *
 * @phpstan-import-type SourceValue from SourceCells
 */
final readonly class SourceRowView
{
    private const string HIDDEN = 'driving_data.hidden';

    /**
     * @param list<SourceColumnDto> $columns
     * @param bool                  $rowKeyHidden the row id is the person id, or no mapping tells that it is not
     */
    private function __construct(
        public array $columns,
        public bool $mappingMissing,
        public bool $rowKeyHidden,
    ) {
    }

    /**
     * @param array<array-key, SourceValue> $values  the copy, keyed by the column name
     * @param MappingDto|null               $mapping the mapping of the import, or null if it no longer exists
     */
    public static function forStopRow(array $values, ?MappingDto $mapping): self
    {
        return null === $mapping ? self::missing() : self::create($values, $mapping, []);
    }

    /**
     * Marks the column of the field that caused the rejection. With no field, the row id caused
     * the rejection, so the row id columns are marked.
     *
     * @param array<array-key, SourceValue> $values  the copy, keyed by the column name
     * @param MappingDto|null               $mapping the mapping of the import, or null if it no longer exists
     */
    public static function forRejectedRow(array $values, ?MappingDto $mapping, ?FieldName $field): self
    {
        if (null === $mapping) {
            return self::missing();
        }

        return self::create($values, $mapping, null === $field ? $mapping->rowId : [$mapping->field($field)->column]);
    }

    private static function missing(): self
    {
        return new self([], true, true);
    }

    /**
     * @param array<array-key, SourceValue> $values
     * @param list<string>                  $causes
     */
    private static function create(array $values, MappingDto $mapping, array $causes): self
    {
        $personId = $mapping->field(FieldName::PersonId)->column;
        $hiddenColumns = [$personId, ...$mapping->hiddenColumns];
        $columns = [];
        foreach ($values as $column => $value) {
            $column = (string) $column;
            $cause = \in_array($column, $causes, true);
            if (\in_array($column, $hiddenColumns, true)) {
                $columns[] = new SourceColumnDto($column, null, self::HIDDEN, true, $cause);
                continue;
            }
            $text = SourceValueFormatter::format($value);
            $columns[] = $text instanceof TranslatableMessage
                ? new SourceColumnDto($column, null, $text->getMessage(), false, $cause)
                : new SourceColumnDto($column, $text, null, false, $cause);
        }
        // The copy leaves out an empty cell, but an empty cause must show.
        $copied = array_map(static fn (int|string $column): string => (string) $column, array_keys($values));
        foreach (array_diff($causes, $copied) as $column) {
            $hidden = \in_array($column, $hiddenColumns, true);
            $columns[] = new SourceColumnDto($column, null, $hidden ? self::HIDDEN : 'driving_data.empty', $hidden, true);
        }

        return new self($columns, false, \in_array($personId, $mapping->rowId, true));
    }
}

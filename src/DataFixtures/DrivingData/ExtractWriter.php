<?php

namespace App\DataFixtures\DrivingData;

use App\Import\Mapping\FieldMappingDto;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\FieldType;
use App\Import\Mapping\MappingDto;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateIntervalCell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes a driving data extract in the layout of an import mapping.
 *
 * The tests and the local site use these files, so that no real extract is necessary. The rows
 * have application values. The writer changes them to the cell values of the mapping.
 */
final class ExtractWriter
{
    public const string TITLE = 'Generated driving data';

    private readonly Style $dateStyle;
    private readonly Style $dateTimeStyle;
    private readonly Style $timeStyle;

    public function __construct()
    {
        $this->dateStyle = new Style(format: 'yyyy-mm-dd');
        $this->dateTimeStyle = new Style(format: 'yyyy-mm-dd hh:mm:ss');
        // A duration format, so that a time can be 24:00 or more.
        $this->timeStyle = new Style(format: '[hh]:mm:ss');
    }

    /**
     * @param iterable<ExtractRow> $rows
     * @param list<FieldName>      $omittedFields the fields that get no column
     *
     * @throws \InvalidArgumentException if a value does not agree with the mapping
     */
    public function write(string $path, MappingDto $mapping, iterable $rows, array $omittedFields = []): void
    {
        $fields = array_filter(
            FieldName::cases(),
            static fn (FieldName $name): bool => !\in_array($name, $omittedFields, true),
        );
        $fieldColumns = array_map(static fn (FieldName $name): string => $mapping->field($name)->column, $fields);
        // A row id column can also be the column of a field. Then the field gives the value.
        $rowIdColumns = array_values(array_diff($mapping->rowId, $fieldColumns));

        $writer = new Writer();
        $writer->openToFile($path);
        for ($sheet = 0; $sheet < $mapping->sheet; ++$sheet) {
            $writer->addNewSheetAndMakeItCurrent();
        }
        for ($rowNumber = 1; $rowNumber < $mapping->headerRow; ++$rowNumber) {
            $writer->addRow(Row::fromValues(1 === $rowNumber ? [self::TITLE] : []));
        }
        // The kept columns and then the excluded columns come last. Each of these columns is never
        // a field column or a row id column.
        $writer->addRow(Row::fromValues([...$rowIdColumns, ...$fieldColumns, ...$mapping->keepColumns, ...$mapping->excludeColumns]));

        $names = array_column(FieldName::cases(), 'value');
        $index = 0;
        foreach ($rows as $row) {
            ++$index;
            foreach (array_diff([...array_keys($row->values), ...array_keys($row->raw)], $names) as $unknown) {
                throw new \InvalidArgumentException(\sprintf('The row has a value for "%s", which is not a field.', $unknown));
            }
            foreach (array_diff(array_keys($row->kept), $mapping->keepColumns) as $unknown) {
                throw new \InvalidArgumentException(\sprintf('The row has a value for the column "%s", which is not kept.', $unknown));
            }
            $rowId = $row->rowId ?? (string) $index;
            $cells = array_map(static fn (): Cell => new StringCell($rowId), $rowIdColumns);
            foreach ($fields as $name) {
                $cells[] = \array_key_exists($name->value, $row->raw)
                    ? Cell::fromValue($row->raw[$name->value])
                    : $this->cell($name, $mapping->field($name), $row->values[$name->value] ?? null);
            }
            foreach ($mapping->keepColumns as $column) {
                $cells[] = Cell::fromValue($row->kept[$column] ?? null);
            }
            // The fixtures have no personal data, so an excluded column is empty.
            foreach ($mapping->excludeColumns as $ignored) {
                $cells[] = new EmptyCell(null);
            }
            $writer->addRow(new Row($cells));
        }

        $writer->close();
    }

    private function cell(FieldName $name, FieldMappingDto $field, mixed $value): Cell
    {
        if (null === $value) {
            return new EmptyCell(null);
        }

        return match (true) {
            FieldType::String === $field->type && \is_string($value) => new StringCell(self::code($name, $field, $value)),
            FieldType::Int === $field->type && \is_int($value),
            FieldType::Float === $field->type && (\is_int($value) || \is_float($value)) => new NumericCell($value),
            // MappingConfiguration requires a non-empty list of true and false values for a bool field.
            FieldType::Bool === $field->type && \is_bool($value) => new StringCell($value ? $field->trueValues[0] : $field->falseValues[0]),
            FieldType::Time === $field->type && $value instanceof \DateInterval => new DateIntervalCell($value, $this->timeStyle),
            FieldType::Date === $field->type && $value instanceof \DateTimeInterface => $this->dateCell($field, $value, $this->dateStyle),
            FieldType::DateTime === $field->type && $value instanceof \DateTimeInterface => $this->dateCell($field, $value, $this->dateTimeStyle),
            default => throw new \InvalidArgumentException(\sprintf('The value of "%s" does not have the type of the field.', $name->value)),
        };
    }

    /**
     * Gives the source code of an application value, if the field has a value map.
     */
    private static function code(FieldName $name, FieldMappingDto $field, string $value): string
    {
        if ([] === $field->valueMap) {
            return $value;
        }

        $code = array_search($value, $field->valueMap, true);
        if (false === $code) {
            throw new \InvalidArgumentException(\sprintf('The value of "%s" is not in the mapping.', $name->value));
        }

        return (string) $code;
    }

    /**
     * A date with a format string is text in the extract. Other dates are date cells.
     */
    private function dateCell(FieldMappingDto $field, \DateTimeInterface $value, Style $style): Cell
    {
        if (null !== $field->format && FieldMappingDto::EXCEL_FORMAT !== $field->format) {
            return new StringCell($value->format($field->format));
        }

        return new DateTimeCell($value, $style);
    }
}

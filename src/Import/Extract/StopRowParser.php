<?php

namespace App\Import\Extract;

use App\Import\ImportRangeDto;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Sheet;

/**
 * Reads the stop rows of an extract through an import mapping.
 *
 * The parser finds the columns by the names in the mapping. It never refers to a column name
 * itself. See "Pipeline" in docs/architecture.md.
 *
 * @phpstan-import-type StopRowValues from StopRowDto
 */
final readonly class StopRowParser
{
    /**
     * The unit separator of ASCII. It joins the values of the row id columns to one key.
     */
    private const string ROW_KEY_SEPARATOR = "\u{1F}";

    public function __construct(
        private CellConverter $converter,
        private ExtractInspector $inspector,
        private ExtractLimitsDto $limits,
    ) {
    }

    /**
     * Gives the rows one at a time, so the memory use does not grow with the file size. The
     * parser skips the empty rows.
     *
     * The parser checks and opens the file when the caller asks for the first row. Then it can
     * throw the exception. A file above a limit gives no rows.
     *
     * @return \Generator<int, StopRowDto|RejectedRowDto>
     *
     * @throws InvalidExtractException if the extract is above a limit, is not a valid xlsx file, or does not have the sheet, the header row or a column of the mapping
     */
    public function parse(string $path, MappingDto $mapping, ImportRangeDto $range): \Generator
    {
        $this->inspector->inspect($path, $this->limits);

        // With the empty rows, the key of a row is its row number in the sheet.
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));

        try {
            $reader->open($path);
            // The columns are null until the header row. The rows above the header are not read.
            $columns = null;
            /** @var int $rowNumber RowIterator::key() gives the row number. */
            foreach (self::sheet($reader, $mapping->sheet)->getRowIterator() as $rowNumber => $row) {
                if ($rowNumber === $mapping->headerRow) {
                    $columns = self::columns($row, $mapping);
                } elseif (null !== $columns && !self::isEmpty($row)) {
                    yield $this->stopRow($rowNumber, $row, $mapping, $columns[0], $columns[1], $range);
                }
            }

            if (null === $columns) {
                throw new InvalidExtractException(\sprintf('The extract has no header row %d.', $mapping->headerRow));
            }
        } catch (OpenSpoutException) {
            // The message of OpenSpout can contain text from the file, so it is not kept.
            throw new InvalidExtractException('The extract is not a valid xlsx file.');
        } finally {
            $reader->close();
        }
    }

    private static function sheet(Reader $reader, int $index): Sheet
    {
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getIndex() === $index) {
                return $sheet;
            }
        }

        throw new InvalidExtractException(\sprintf('The extract has no sheet with the index %d.', $index));
    }

    /**
     * Finds the index of the column of each field and each row id column.
     *
     * @return array{array<string, int>, list<int>} the field columns keyed by the FieldName value, and the row id columns
     */
    private static function columns(Row $header, MappingDto $mapping): array
    {
        $indexes = [];
        foreach ($header->cells as $index => $cell) {
            $name = $cell->getValue();
            if (\is_string($name)) {
                // If two columns have the same name, the first column is used.
                $indexes[trim($name)] ??= $index;
            }
        }

        $fieldColumns = [];
        foreach (FieldName::cases() as $name) {
            $column = $mapping->field($name)->column;
            $fieldColumns[$name->value] = $indexes[$column]
                ?? throw new InvalidExtractException(\sprintf('The extract has no column "%s" for the field "%s".', $column, $name->value));
        }

        $rowIdColumns = array_map(
            static fn (string $column): int => $indexes[$column]
                ?? throw new InvalidExtractException(\sprintf('The extract has no column "%s" for the row id.', $column)),
            $mapping->rowId,
        );

        return [$fieldColumns, $rowIdColumns];
    }

    /**
     * @param array<string, int> $fieldColumns
     * @param list<int>          $rowIdColumns
     */
    private function stopRow(int $rowNumber, Row $row, MappingDto $mapping, array $fieldColumns, array $rowIdColumns, ImportRangeDto $range): StopRowDto|RejectedRowDto
    {
        $values = [];
        foreach (FieldName::cases() as $name) {
            $field = $mapping->field($name);
            try {
                $value = $this->converter->convert($field, $row->cells[$fieldColumns[$name->value]] ?? null);
            } catch (RejectedValueException $exception) {
                return new RejectedRowDto($rowNumber, $exception->reason, $name);
            }
            if (null === $value && $field->required) {
                return new RejectedRowDto($rowNumber, RejectionReason::Empty, $name);
            }
            $values[$name->value] = $value;
        }

        $rowKey = null;
        if ([] !== $rowIdColumns) {
            $parts = array_map(static fn (int $index): string => self::text($row->cells[$index] ?? new EmptyCell(null)), $rowIdColumns);
            if (\in_array('', $parts, true)) {
                return new RejectedRowDto($rowNumber, RejectionReason::InvalidRowId, null);
            }
            $rowKey = implode(self::ROW_KEY_SEPARATOR, $parts);
        }

        /** @var StopRowValues $values */
        $stopRow = StopRowDto::fromValues($rowNumber, $rowKey, $values);

        return $range->contains($stopRow->serviceDate)
            ? $stopRow
            : new RejectedRowDto($rowNumber, RejectionReason::OutsideRange, FieldName::ServiceDate);
    }

    /**
     * Gives the text of a row id cell. A cell that is not text or a whole number gives "".
     */
    private static function text(Cell $cell): string
    {
        $value = $cell->getValue();

        return \is_string($value) || \is_int($value) ? trim((string) $value) : '';
    }

    /**
     * Row::isEmpty() does not see a row with only spaces as empty.
     */
    private static function isEmpty(Row $row): bool
    {
        return array_all(
            $row->cells,
            static function (Cell $cell): bool {
                $value = $cell->getValue();

                return null === $value || (\is_string($value) && '' === trim($value));
            },
        );
    }
}

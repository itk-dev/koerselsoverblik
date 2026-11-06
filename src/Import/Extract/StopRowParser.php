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
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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

    /**
     * @param string $timeZone the value of APP_TIMEZONE, the time zone of the wall-clock times in the extract
     */
    public function __construct(
        private CellConverter $converter,
        private ExtractInspector $inspector,
        #[Autowire('%app.timezone%')]
        private string $timeZone,
    ) {
    }

    /**
     * Gives the rows one at a time, so the memory use does not grow with the file size. The
     * parser skips the empty rows.
     *
     * The parser checks and opens the file when the caller asks for the first row. Then it can
     * throw the exception. A file above a limit gives no rows.
     *
     * @param ?ImportRangeDto $range the dates of the upload, or null to accept all dates
     *
     * @return \Generator<int, StopRowDto|RejectedRowDto>
     *
     * @throws MissingColumnsException if the extract does not have all the columns of the mapping
     * @throws InvalidExtractException if the extract is above a limit, is not a valid xlsx file, or does not have the sheet or the header row
     */
    public function parse(string $path, MappingDto $mapping, ?ImportRangeDto $range): \Generator
    {
        $this->inspector->inspect($path);

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
                    yield $this->stopRow($rowNumber, $row, $mapping, $columns[0], $columns[1], $columns[2], $range);
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
     * Finds the index of the column of each field and each row id column, and of the columns of
     * the copy.
     *
     * The copy has the field columns, the row id columns and the kept columns, in the order of the
     * header. A kept column that is not in the header is not an error.
     *
     * @return array{array<string, int>, list<int>, array<string, int>} the field columns keyed by the FieldName value, the row id columns, and the columns of the copy keyed by the column name
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
            $fieldColumns[$name->value] = $mapping->field($name)->column;
        }

        $missing = array_values(array_unique(array_diff([...$fieldColumns, ...$mapping->rowId], array_keys($indexes))));
        if ([] !== $missing) {
            throw new MissingColumnsException($missing);
        }

        $copyColumns = array_flip([...$fieldColumns, ...$mapping->rowId, ...$mapping->keepColumns]);

        return [
            array_map(static fn (string $column): int => $indexes[$column], $fieldColumns),
            array_map(static fn (string $column): int => $indexes[$column], $mapping->rowId),
            array_intersect_key($indexes, $copyColumns),
        ];
    }

    /**
     * @param array<string, int> $fieldColumns
     * @param list<int>          $rowIdColumns
     * @param array<string, int> $copyColumns
     */
    private function stopRow(int $rowNumber, Row $row, MappingDto $mapping, array $fieldColumns, array $rowIdColumns, array $copyColumns, ?ImportRangeDto $range): StopRowDto|RejectedRowDto
    {
        $copy = SourceCells::copy(array_map(static fn (int $index): Cell => $row->cells[$index] ?? new EmptyCell(null), $copyColumns));
        $reject = fn (RejectionReason $reason, ?FieldName $name): RejectedRowDto => new RejectedRowDto(
            $rowNumber,
            $reason,
            $name,
            $this->serviceDate($row, $mapping, $fieldColumns),
            $copy,
        );

        $values = [];
        $warnings = [];
        foreach (FieldName::cases() as $name) {
            $field = $mapping->field($name);
            try {
                $value = $this->converter->convert($field, $row->cells[$fieldColumns[$name->value]] ?? null);
            } catch (RejectedValueException $exception) {
                // An optional field gives a warning, so that one bad value does not reject the row.
                if ($field->required) {
                    return $reject($exception->reason, $name);
                }
                $warnings[] = new FieldWarningDto($name, $exception->reason);
                $value = null;
            }
            if (null === $value && $field->required) {
                return $reject(RejectionReason::Empty, $name);
            }
            $values[$name->value] = $value;
        }

        $rowKey = null;
        if ([] !== $rowIdColumns) {
            $parts = array_map(static fn (int $index): string => self::text($row->cells[$index] ?? new EmptyCell(null)), $rowIdColumns);
            if (\in_array('', $parts, true)) {
                return $reject(RejectionReason::InvalidRowId, null);
            }
            $rowKey = implode(self::ROW_KEY_SEPARATOR, $parts);
        }

        /** @var StopRowValues $values */
        $stopRow = StopRowDto::fromValues($rowNumber, $rowKey, $values, new \DateTimeZone($this->timeZone), $copy, $warnings);

        // The upload for the date of the row imports it, so the row has no copy here.
        return null === $range || $range->contains($stopRow->serviceDate)
            ? $stopRow
            : new RejectedRowDto($rowNumber, RejectionReason::OutsideRange, FieldName::ServiceDate, $stopRow->serviceDate);
    }

    /**
     * Gives the service date of a rejected row, or null if its date field is not valid.
     *
     * @param array<string, int> $fieldColumns
     */
    private function serviceDate(Row $row, MappingDto $mapping, array $fieldColumns): ?\DateTimeImmutable
    {
        $name = FieldName::ServiceDate;
        try {
            $value = $this->converter->convert($mapping->field($name), $row->cells[$fieldColumns[$name->value]] ?? null);
        } catch (RejectedValueException) {
            return null;
        }

        return $value instanceof \DateTimeImmutable ? StopRowDto::serviceDate($value) : null;
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

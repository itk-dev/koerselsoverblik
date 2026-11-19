<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Extract;

use App\Import\Extract\SourceCells;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\BooleanCell;
use OpenSpout\Common\Entity\Cell\DateIntervalCell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Cell\TextRunCell;
use OpenSpout\Common\Entity\Comment\TextRun;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the copy of a row keeps the type of each cell, so that the parser can read the copy
 * again in the same way as the row of the file.
 */
final class SourceCellsTest extends TestCase
{
    public function testCopyKeepsTheTypeOfEachCell(): void
    {
        $copy = SourceCells::copy([
            'Text' => new StringCell(' Ø4-VB-01 '),
            'Whole' => new NumericCell(3),
            'Decimal' => new NumericCell(2.5),
            'Flag' => new BooleanCell(true),
            'Time' => new DateIntervalCell(new \DateInterval('PT25H10M5S')),
            'Moment' => new DateTimeCell(new \DateTimeImmutable('2026-09-18 06:51:56.250000')),
            'Error' => new ErrorCell('#VALUE!'),
            'Formula' => new FormulaCell('=A1+1', 4),
            'Formula time' => new FormulaCell('=B1', new \DateInterval('PT7H10M')),
        ]);

        self::assertSame([
            // The copy keeps the spaces. The parser removes them when it reads the copy.
            'Text' => ' Ø4-VB-01 ',
            'Whole' => 3,
            'Decimal' => 2.5,
            'Flag' => true,
            'Time' => ['duration' => 'P0DT25H10M5S'],
            'Moment' => ['datetime' => '2026-09-18 06:51:56.250000'],
            'Error' => ['error' => '#VALUE!'],
            // A formula cell gives its computed value, as the parser reads it.
            'Formula' => 4,
            'Formula time' => ['duration' => 'P0DT7H10M0S'],
        ], $copy);
    }

    /**
     * The reader gives no computed value when the result of the formula is an error.
     */
    public function testCopyLeavesOutAFormulaWithNoComputedValue(): void
    {
        self::assertSame([], SourceCells::copy(['Formula' => new FormulaCell('=A1/0', null)]));
    }

    public function testCopyLeavesOutAnEmptyCell(): void
    {
        self::assertSame(['Text' => 'A'], SourceCells::copy(['Empty' => new EmptyCell(null), 'Text' => new StringCell('A'), 'Blank' => new StringCell('')]));
    }

    /**
     * The XLSX reader gives no cell with rich text, so the copy has no type for it.
     */
    public function testCopyLeavesOutACellWithRichText(): void
    {
        self::assertSame([], SourceCells::copy(['Rich' => new TextRunCell([new TextRun('A')], null, null)]));
    }

    /**
     * @param array<array-key, Cell> $cells
     */
    #[DataProvider('rows')]
    public function testCellsFromACopyGiveTheSameValues(array $cells): void
    {
        // The JSON flags of the Doctrine json type, so that 3.0 stays a decimal.
        $json = json_encode(SourceCells::copy($cells), \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
        /** @var array<array-key, mixed> $decoded */
        $decoded = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);

        $result = SourceCells::cells($decoded);

        self::assertSame(array_keys($cells), array_keys($result));
        foreach ($cells as $column => $cell) {
            self::assertInstanceOf($cell::class, $result[$column], (string) $column);
            self::assertEquals($cell->getValue(), $result[$column]->getValue(), (string) $column);
        }
    }

    /**
     * @return iterable<string, array{array<array-key, Cell>}>
     */
    public static function rows(): iterable
    {
        yield 'text' => [['Text' => new StringCell(' A ')]];
        yield 'whole number' => [['Whole' => new NumericCell(3)]];
        yield 'decimal with no fraction' => [['Decimal' => new NumericCell(3.0)]];
        yield 'decimal' => [['Decimal' => new NumericCell(2.5)]];
        yield 'boolean' => [['Flag' => new BooleanCell(false)]];
        yield 'time after midnight' => [['Time' => new DateIntervalCell(new \DateInterval('PT25H10M0S'))]];
        yield 'time with days' => [['Time' => new DateIntervalCell(new \DateInterval('P1DT1H10M0S'))]];
        yield 'date and time' => [['Moment' => new DateTimeCell(new \DateTimeImmutable('2026-03-29 02:30:00.125000'))]];
        // Cell::fromValue() makes a formula cell from text that starts with "=".
        yield 'text that starts with "="' => [['Text' => new StringCell('=abc')]];
        yield 'several' => [['A' => new StringCell('x'), 'B' => new NumericCell(1)]];
        // PHP changes the key "2026" to an int, also after json_decode().
        yield 'numeric column name' => [['2026' => new NumericCell(5)]];
    }

    /**
     * The parser rejects an error cell, so the cell from a copy must also be an error cell.
     */
    public function testErrorCellFromACopyIsAnErrorCell(): void
    {
        $cell = SourceCells::cells(['Error' => ['error' => '#N/A']])['Error'];

        self::assertInstanceOf(ErrorCell::class, $cell);
        self::assertSame('#N/A', $cell->getRawValue());
    }

    /**
     * The date and time is a wall-clock time. It keeps the time when the PHP default time zone
     * changes.
     */
    public function testDateAndTimeIsAWallClockTime(): void
    {
        $copy = SourceCells::copy(['Moment' => new DateTimeCell(new \DateTimeImmutable('2026-09-18 06:51:56', new \DateTimeZone('UTC')))]);

        self::assertSame(['Moment' => ['datetime' => '2026-09-18 06:51:56.000000']], $copy);
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('invalidCopies')]
    public function testInvalidCopyIsRejected(array $values): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The copy has a value of an unknown type in the column "Bad".');

        SourceCells::cells($values);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidCopies(): iterable
    {
        yield 'null' => [['Bad' => null]];
        yield 'list' => [['Bad' => ['x']]];
        yield 'unknown key' => [['Bad' => ['colour' => 'red']]];
        yield 'formula' => [['Bad' => ['formula' => '=A1']]];
        yield 'two keys' => [['Bad' => ['duration' => 'PT1H', 'datetime' => '2026-01-01 00:00:00']]];
        yield 'value that is not text' => [['Bad' => ['duration' => 1]]];
    }

    /**
     * The message of a copy that is not valid never has the value, because the value can come from
     * the extract.
     */
    public function testInvalidDurationOrDateIsRejectedWithoutTheValue(): void
    {
        foreach ([['duration' => 'SECRET'], ['datetime' => 'SECRET']] as $value) {
            try {
                SourceCells::cells(['Bad' => $value]);
                self::fail('The copy was not rejected.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('The copy has a value of an unknown type in the column "Bad".', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }
}

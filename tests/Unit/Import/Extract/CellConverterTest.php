<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Extract;

use App\Import\Extract\CellConverter;
use App\Import\Extract\RejectedValueException;
use App\Import\Extract\RejectionReason;
use App\Import\Mapping\FieldMappingDto;
use App\Import\Mapping\FieldType;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the conversion of one cell to an application value, for each field type.
 */
final class CellConverterTest extends TestCase
{
    /**
     * @param \Closure(): ?Cell $cell
     */
    #[DataProvider('emptyCells')]
    public function testEmptyCellGivesNull(\Closure $cell): void
    {
        foreach (FieldType::cases() as $type) {
            self::assertNull(new CellConverter()->convert(self::field($type), $cell()), $type->value);
        }
    }

    /**
     * @return iterable<string, array{\Closure(): ?Cell}>
     */
    public static function emptyCells(): iterable
    {
        yield 'no cell' => [static fn (): null => null];
        yield 'an empty cell' => [static fn (): Cell => new EmptyCell(null)];
        yield 'empty text' => [static fn (): Cell => Cell::fromValue('')];
        yield 'only spaces' => [static fn (): Cell => Cell::fromValue('   ')];
        // The reader gives no computed value when the result of the formula is an error.
        yield 'a formula with no computed value' => [static fn (): Cell => new FormulaCell('=A1/0', null)];
    }

    public function testErrorCellIsRejected(): void
    {
        self::assertRejected(RejectionReason::ErrorValue, self::field(FieldType::Int), new ErrorCell('#N/A', null));
    }

    /**
     * The value of a formula cell is the formula. The computed value is the value in the cell.
     */
    #[DataProvider('formulaCells')]
    public function testFormulaCellGivesTheComputedValue(FieldType $type, \DateInterval|int|string $computed, mixed $expected): void
    {
        $value = new CellConverter()->convert(self::field($type), new FormulaCell('=A1', $computed));

        self::assertEquals($expected, $value);
    }

    /**
     * @return iterable<string, array{FieldType, \DateInterval|int|string, mixed}>
     */
    public static function formulaCells(): iterable
    {
        yield 'a whole number' => [FieldType::Int, 4, 4];
        yield 'text' => [FieldType::String, ' R-01 ', 'R-01'];
        yield 'a time' => [FieldType::Time, new \DateInterval('PT7H10M'), new \DateInterval('PT7H10M')];
    }

    public function testTextHasNoSpacesAtTheStartAndTheEnd(): void
    {
        self::assertSame('P-001', self::convert(self::field(FieldType::String), ' P-001 '));
    }

    public function testWholeNumberIsText(): void
    {
        self::assertSame('101', self::convert(self::field(FieldType::String), 101));
    }

    public function testOtherValueIsNotText(): void
    {
        self::assertRejected(RejectionReason::NotText, self::field(FieldType::String), Cell::fromValue(1.5));
        self::assertRejected(RejectionReason::NotText, self::field(FieldType::String), Cell::fromValue(new \DateTimeImmutable()));
    }

    public function testValueMapGivesTheApplicationValue(): void
    {
        $field = self::field(FieldType::String, map: ['IN' => 'pickup', '2' => 'drop_off']);

        self::assertSame('pickup', self::convert($field, ' IN '));
        self::assertSame('drop_off', self::convert($field, 2));
    }

    public function testCodeThatIsNotInTheValueMapIsRejected(): void
    {
        self::assertRejected(RejectionReason::UnknownCode, self::field(FieldType::String, map: ['IN' => 'pickup']), Cell::fromValue('in'));
    }

    public function testWholeNumber(): void
    {
        self::assertSame(3, self::convert(self::field(FieldType::Int), 3));
    }

    public function testOtherValueIsNotAWholeNumber(): void
    {
        $field = self::field(FieldType::Int);

        self::assertRejected(RejectionReason::NotWholeNumber, $field, Cell::fromValue(2.5));
        self::assertRejected(RejectionReason::NotWholeNumber, $field, Cell::fromValue('3'));
    }

    public function testNumber(): void
    {
        $field = self::field(FieldType::Float);

        self::assertSame(2.0, self::convert($field, 2));
        self::assertSame(3.5, self::convert($field, 3.5));
    }

    public function testTextIsNotANumber(): void
    {
        self::assertRejected(RejectionReason::NotNumber, self::field(FieldType::Float), Cell::fromValue('far'));
    }

    public function testTrueAndFalseValues(): void
    {
        $field = self::field(FieldType::Bool, true: ['Solo', '1'], false: ['Samkørsel', '-']);

        self::assertTrue(self::convert($field, ' Solo '));
        self::assertTrue(self::convert($field, 1));
        self::assertFalse(self::convert($field, 'Samkørsel'));
        self::assertFalse(self::convert($field, '-'));
    }

    public function testValueInNeitherListIsRejected(): void
    {
        $field = self::field(FieldType::Bool, true: ['Solo'], false: ['Samkørsel']);

        self::assertRejected(RejectionReason::UnknownBoolValue, $field, Cell::fromValue('solo'));
        self::assertRejected(RejectionReason::UnknownBoolValue, $field, Cell::fromValue(new \DateTimeImmutable()));
    }

    #[DataProvider('dateTypes')]
    public function testDateCell(FieldType $type): void
    {
        $value = self::convert(self::field($type), new \DateTimeImmutable('2026-01-05 07:12:30'));

        self::assertDateTime('2026-01-05 07:12:30', $value);
    }

    #[DataProvider('dateTypes')]
    public function testExcelSerialNumber(FieldType $type): void
    {
        $field = self::field($type, format: FieldMappingDto::EXCEL_FORMAT);

        self::assertDateTime('2026-01-05 00:00:00', self::convert($field, 46027));
        // 0.3 days is 07:12.
        self::assertDateTime('2026-01-05 07:12:00', self::convert($field, 46027.3));
    }

    /**
     * The clocks go forward from 02:00 to 03:00 on 29 March 2026. The time stays a wall-clock
     * time, also in the hour that does not exist in Europe/Copenhagen.
     */
    public function testExcelSerialNumberOnTheDayOfTheChangeToSummerTime(): void
    {
        $field = self::field(FieldType::DateTime);

        self::assertDateTime('2026-03-29 07:12:00', self::convert($field, 46110.3));
        self::assertDateTime('2026-03-29 02:30:00', self::convert($field, 46110 + 2.5 / 24));
    }

    /**
     * Excel stores a time of day as a fraction. The conversion rounds it to whole seconds.
     */
    public function testExcelSerialNumberIsRoundedToSeconds(): void
    {
        $field = self::field(FieldType::DateTime);

        self::assertDateTime('2026-01-05 00:00:30', self::convert($field, 46027 + 30.4 / 86400));
        self::assertDateTime('2026-01-05 00:00:31', self::convert($field, 46027 + 30.6 / 86400));
    }

    #[DataProvider('dateTypes')]
    public function testDateWithAFormat(FieldType $type): void
    {
        self::assertDateTime('2026-01-05 00:00:00', self::convert(self::field($type, format: 'Y-m-d'), '2026-01-05'));
        self::assertDateTime('2026-01-05 07:12:00', self::convert(self::field($type, format: 'd.m.Y H:i'), '05.01.2026 07:12'));
        // 02:30 does not exist on 29 March 2026 in Europe/Copenhagen. The time stays a wall-clock time.
        self::assertDateTime('2026-03-29 02:30:00', self::convert(self::field($type, format: 'Y-m-d H:i'), '2026-03-29 02:30'));
    }

    /**
     * Excel can change the text of a date to a date cell when a user saves the file.
     */
    #[DataProvider('dateTypes')]
    public function testDateCellForAFieldWithAFormat(FieldType $type): void
    {
        $value = self::convert(self::field($type, format: 'Y-m-d'), new \DateTimeImmutable('2026-01-05'));

        self::assertDateTime('2026-01-05 00:00:00', $value);
    }

    /**
     * @return iterable<string, array{FieldType}>
     */
    public static function dateTypes(): iterable
    {
        yield 'date' => [FieldType::Date];
        yield 'datetime' => [FieldType::DateTime];
    }

    #[DataProvider('badDates')]
    public function testBadDateIsRejected(?string $format, \DateInterval|\DateTimeInterface|int|string $value): void
    {
        self::assertRejected(RejectionReason::NotDate, self::field(FieldType::Date, format: $format), Cell::fromValue($value));
        self::assertRejected(RejectionReason::NotDateTime, self::field(FieldType::DateTime, format: $format), Cell::fromValue($value));
    }

    /**
     * @return iterable<string, array{?string, \DateInterval|\DateTimeInterface|int|string}>
     */
    public static function badDates(): iterable
    {
        yield 'text for a date cell' => [null, '2026-01-05'];
        yield 'text for an Excel date' => [FieldMappingDto::EXCEL_FORMAT, '2026-01-05'];
        yield 'a time for an Excel date' => [FieldMappingDto::EXCEL_FORMAT, new \DateInterval('PT7H')];
        yield 'text that is not a date' => ['Y-m-d', 'not-a-date'];
        yield 'a date that does not exist' => ['Y-m-d', '2026-02-30'];
        yield 'text after the date' => ['Y-m-d', '2026-01-05 07:12'];
        yield 'a number for a format' => ['Y-m-d', 46027];
    }

    public function testTime(): void
    {
        $value = self::convert(self::field(FieldType::Time), new \DateInterval('PT25H10M'));

        self::assertInstanceOf(\DateInterval::class, $value);
        self::assertSame('25:10:00', $value->format('%h:%I:%S'));
    }

    public function testOtherValueIsNotATime(): void
    {
        $field = self::field(FieldType::Time);

        self::assertRejected(RejectionReason::NotTime, $field, Cell::fromValue('late'));
        self::assertRejected(RejectionReason::NotTime, $field, Cell::fromValue(0.5));
        self::assertRejected(RejectionReason::NotTime, $field, Cell::fromValue(new \DateTimeImmutable('2026-01-05 07:12')));
    }

    /**
     * @param array<array-key, string> $map
     * @param list<string>             $true
     * @param list<string>             $false
     */
    private static function field(FieldType $type, ?string $format = null, array $map = [], array $true = [], array $false = []): FieldMappingDto
    {
        return new FieldMappingDto(
            column: 'Column',
            type: $type,
            required: false,
            format: $format,
            valueMap: $map,
            trueValues: $true,
            falseValues: $false,
        );
    }

    private static function convert(FieldMappingDto $field, \DateInterval|\DateTimeInterface|float|int|string $value): mixed
    {
        return new CellConverter()->convert($field, Cell::fromValue($value));
    }

    private static function assertRejected(RejectionReason $reason, FieldMappingDto $field, Cell $cell): void
    {
        try {
            new CellConverter()->convert($field, $cell);
            self::fail(\sprintf('The value was not rejected with "%s".', $reason->value));
        } catch (RejectedValueException $exception) {
            self::assertSame($reason, $exception->reason);
        }
    }

    private static function assertDateTime(string $expected, mixed $value): void
    {
        self::assertInstanceOf(\DateTimeImmutable::class, $value);
        self::assertSame($expected, $value->format('Y-m-d H:i:s'));
    }
}

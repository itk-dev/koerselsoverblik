<?php

namespace App\Tests\Unit\Import\Extract;

use App\Import\Extract\ExtractInspector;
use App\Import\Extract\ExtractLimitsDto;
use App\Import\Extract\InvalidExtractException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the inspector rejects an extract that is too large or that has a DOCTYPE.
 *
 * The tests write a workbook with OpenSpout, and then change its XML files with ZipArchive.
 * The writer gives each row and each cell an "r" attribute, and gives the sheet a dimension.
 */
final class ExtractInspectorTest extends TestCase
{
    private const string SHEET = 'xl/worksheets/sheet1.xml';

    /**
     * Three rows of three columns.
     */
    private const array ROWS = [['A', 'B', 'C'], ['D', 'E', 'F'], ['G', 'H', 'I']];

    private string $path = '';

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $writer = new Writer();
        $writer->openToFile($this->path);
        foreach (self::ROWS as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testWorkbookInTheLimitsIsAccepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->inspect(self::limits(maxFileSize: (int) filesize($this->path), maxUncompressedSize: $this->uncompressedSize(), maxRows: 3, maxColumns: 3));
    }

    public function testMissingFileIsRejected(): void
    {
        unlink($this->path);

        $this->expectRejection('The extract is not a file.');

        $this->inspect(self::limits());
    }

    public function testFileAboveTheSizeLimitIsRejected(): void
    {
        $this->expectRejection(\sprintf('The extract is larger than %d bytes.', filesize($this->path) - 1));

        $this->inspect(self::limits(maxFileSize: (int) filesize($this->path) - 1));
    }

    public function testFileThatIsNotAnArchiveIsRejected(): void
    {
        file_put_contents($this->path, "Route;Date\nR-01;2026-01-05\n");

        $this->expectRejection('The extract is not an xlsx file.');

        $this->inspect(self::limits());
    }

    public function testArchiveAboveTheUncompressedSizeLimitIsRejected(): void
    {
        $size = $this->uncompressedSize();

        $this->expectRejection(\sprintf('The extract is larger than %d bytes when it is not compressed.', $size - 1));

        $this->inspect(self::limits(maxUncompressedSize: $size - 1));
    }

    /**
     * OpenSpout reads all of these files, so a DOCTYPE in any of them is rejected.
     */
    #[DataProvider('xmlFiles')]
    public function testDoctypeWithAnExternalEntityIsRejected(string $entry): void
    {
        $this->changeEntry($entry, static fn (string $xml): string => (string) preg_replace(
            '/^(<\?xml[^>]*\?>)/',
            '$1<!-- A comment comes before the DOCTYPE. --><!DOCTYPE x [<!ENTITY xxe SYSTEM "file:///etc/hostname">]>',
            $xml,
        ));

        $this->expectRejection('The extract has a DOCTYPE.');

        $this->inspect(self::limits());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function xmlFiles(): iterable
    {
        yield 'sheet' => [self::SHEET];
        yield 'shared strings' => ['xl/sharedStrings.xml'];
        yield 'workbook' => ['xl/workbook.xml'];
        yield 'relations' => ['xl/_rels/workbook.xml.rels'];
    }

    public function testRowNumberAboveTheLimitIsRejected(): void
    {
        $this->expectRejection('The extract has more than 2 rows.');

        $this->inspect(self::limits(maxRows: 2));
    }

    /**
     * OpenSpout gives an empty row for each row number below a row, so a high row number in a
     * small file uses much time.
     */
    public function testHighRowNumberIsRejected(): void
    {
        $this->changeSheet(static fn (string $xml): string => str_replace('<row r="3"', '<row r="1000000000"', $xml));

        $this->expectRejection('The extract has more than 1000 rows.');

        $this->inspect(self::limits(maxRows: 1000));
    }

    /**
     * A row without "r" has the number of the row before it, plus one. The count starts again in
     * each XML file.
     */
    public function testRowWithoutNumberIsCountedFromTheRowBeforeIt(): void
    {
        $this->changeSheet(static fn (string $xml): string => (string) preg_replace('/<row r="\d+"/', '<row', $xml));
        $this->copySheet('xl/worksheets/sheet2.xml');

        $this->inspect(self::limits(maxRows: 3));

        $this->expectRejection('The extract has more than 2 rows.');

        $this->inspect(self::limits(maxRows: 2));
    }

    /**
     * OpenSpout uses (int) on the row number, so a row number that is not a number is 0.
     */
    public function testRowNumberThatIsNotANumberIsZero(): void
    {
        $this->changeSheet(static fn (string $xml): string => str_replace('<row r="3"', '<row r="x"', $xml));

        $this->expectNotToPerformAssertions();

        $this->inspect(self::limits(maxRows: 2));
    }

    public function testRowWithAPrefixIsCounted(): void
    {
        $this->changeSheet(static fn (string $xml): string => str_replace(
            ['<worksheet ', '</worksheet>', '<row ', '</row>'],
            ['<x:worksheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ', '</x:worksheet>', '<x:row ', '</x:row>'],
            $xml,
        ));

        $this->expectRejection('The extract has more than 2 rows.');

        $this->inspect(self::limits(maxRows: 2));
    }

    public function testSheetDimensionAboveTheColumnLimitIsRejected(): void
    {
        $this->expectRejection('The extract has more than 2 columns.');

        $this->inspect(self::limits(maxColumns: 2));
    }

    /**
     * OpenSpout fills each row with empty cells to the column in the dimension.
     */
    public function testWideSheetDimensionIsRejected(): void
    {
        $this->changeSheet(static fn (string $xml): string => str_replace('<dimension ref="A1:C3"/>', '<dimension ref="A1:ZZ3"/>', $xml));

        // "ZZ" is column 26 * 26 + 26 = 702.
        $this->inspect(self::limits(maxColumns: 702));

        $this->expectRejection('The extract has more than 701 columns.');

        $this->inspect(self::limits(maxColumns: 701));
    }

    public function testDimensionWithoutReferenceIsAccepted(): void
    {
        $this->changeSheet(static fn (string $xml): string => str_replace('<dimension ref="A1:C3"/>', '<dimension/>', $xml));

        $this->expectNotToPerformAssertions();

        $this->inspect(self::limits(maxColumns: 3));
    }

    /**
     * Without a dimension, OpenSpout makes each row as wide as the "spans" of the row. It does not
     * check the value, so a small file can use all the memory.
     */
    #[DataProvider('wideSpans')]
    public function testWideSpansIsRejected(string $spans): void
    {
        $this->changeSheet(static fn (string $xml): string => str_replace('<row r="2" spans="1:3"', '<row r="2" spans="'.$spans.'"', self::withoutDimension($xml)));

        $this->expectRejection('The extract has more than 3 columns.');

        $this->inspect(self::limits(maxColumns: 3));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wideSpans(): iterable
    {
        yield 'number' => ['1:100000000'];
        yield 'exponent' => ['1:1e9'];
        yield 'list' => ['1:3 5:9'];
    }

    public function testCellAboveTheColumnLimitIsRejected(): void
    {
        $this->changeSheet(static fn (string $xml): string => self::withoutSpans(self::withoutDimension($xml)));

        $this->inspect(self::limits(maxColumns: 3));

        $this->changeSheet(static fn (string $xml): string => str_replace('r="C2"', 'r="D2"', $xml));

        $this->expectRejection('The extract has more than 3 columns.');

        $this->inspect(self::limits(maxColumns: 3));
    }

    /**
     * A cell without "r" is in the column after the cell before it. The count starts again in
     * each row.
     */
    public function testCellWithoutColumnIsCountedFromTheCellBeforeIt(): void
    {
        $this->changeSheet(static fn (string $xml): string => (string) preg_replace('/<c r="[A-Z]+\d+"/', '<c', self::withoutSpans(self::withoutDimension($xml))));

        $this->inspect(self::limits(maxColumns: 3));

        $this->expectRejection('The extract has more than 2 columns.');

        $this->inspect(self::limits(maxColumns: 2));
    }

    /**
     * OpenSpout counts a cell before the first row from the first column.
     */
    public function testCellBeforeTheFirstRowIsCounted(): void
    {
        $this->addEntry('xl/z.xml', '<?xml version="1.0"?><x><c/><c/><c/><c/></x>');

        $this->expectRejection('The extract has more than 3 columns.');

        $this->inspect(self::limits(maxColumns: 3));
    }

    /**
     * A workbook can have files that are not XML, for example the printer settings. The
     * inspector reads the files after them.
     */
    public function testFileThatIsNotXmlIsSkipped(): void
    {
        $this->addEntry('xl/printerSettings/printerSettings1.bin', "\x00\x01\x02 binary");

        $this->inspect(self::limits());

        $this->addEntry('xl/z.xml', '<?xml version="1.0"?><!DOCTYPE x><x/>');

        $this->expectRejection('The extract has a DOCTYPE.');

        $this->inspect(self::limits());
    }

    /**
     * The reader reads a file in parts. It gives the nodes of the parts before an error, and so
     * does OpenSpout. Thus a file with an error after the first part is not skipped.
     */
    public function testXmlFileWithAnErrorIsRejected(): void
    {
        $this->addEntry('xl/z.xml', '<?xml version="1.0"?><root>'.str_repeat('<row/>', 1000).'<bad></root>');

        $this->expectRejection('The extract has an XML file that is not valid.');

        $this->inspect(self::limits());
    }

    /**
     * The libxml errors are global. The inspector ignores the errors of other code, and does not
     * keep its own errors.
     */
    public function testErrorsOfOtherCodeAreNotKept(): void
    {
        $this->addEntry('xl/printerSettings/printerSettings1.bin', "\x00\x01\x02 binary");
        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            simplexml_load_string('<not-closed>');
            $this->inspect(self::limits());

            self::assertSame([], libxml_get_errors());
            self::assertTrue(libxml_use_internal_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }
    }

    public function testLibxmlSettingIsRestoredAfterARejection(): void
    {
        $this->addEntry('xl/z.xml', '<?xml version="1.0"?><!DOCTYPE x><x/>');

        try {
            $this->inspect(self::limits());
            self::fail('The inspector accepted the extract.');
        } catch (InvalidExtractException) {
            self::assertFalse(libxml_use_internal_errors());
        }
    }

    public function testMessageHasNoValueFromTheExtract(): void
    {
        $this->changeSheet(static fn (string $xml): string => str_replace('<row r="3"', '<row r="777777"', $xml));

        try {
            $this->inspect(self::limits(maxRows: 10));
            self::fail('The inspector accepted the extract.');
        } catch (InvalidExtractException $exception) {
            self::assertStringNotContainsString('777777', $exception->getMessage());
        }
    }

    private function inspect(ExtractLimitsDto $limits): void
    {
        (new ExtractInspector())->inspect($this->path, $limits);
    }

    private function expectRejection(string $message): void
    {
        $this->expectException(InvalidExtractException::class);
        $this->expectExceptionMessage($message);
    }

    private function uncompressedSize(): int
    {
        $zip = new \ZipArchive();
        $zip->open($this->path);
        $size = 0;
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            /** @var array{size: int} $stat */
            $stat = $zip->statIndex($index);
            $size += $stat['size'];
        }
        $zip->close();

        return $size;
    }

    /**
     * @param callable(string): string $change
     */
    private function changeSheet(callable $change): void
    {
        $this->changeEntry(self::SHEET, $change);
    }

    /**
     * @param callable(string): string $change
     */
    private function changeEntry(string $entry, callable $change): void
    {
        $zip = new \ZipArchive();
        $zip->open($this->path);
        $zip->addFromString($entry, $change((string) $zip->getFromName($entry)));
        $zip->close();
    }

    private function copySheet(string $entry): void
    {
        $zip = new \ZipArchive();
        $zip->open($this->path);
        $zip->addFromString($entry, (string) $zip->getFromName(self::SHEET));
        $zip->close();
    }

    private function addEntry(string $entry, string $content): void
    {
        $zip = new \ZipArchive();
        $zip->open($this->path);
        $zip->addFromString($entry, $content);
        $zip->close();
    }

    private static function withoutDimension(string $xml): string
    {
        return (string) preg_replace('/<dimension [^>]*\/>/', '', $xml);
    }

    private static function withoutSpans(string $xml): string
    {
        return (string) preg_replace('/ spans="[^"]*"/', '', $xml);
    }

    private static function limits(int $maxFileSize = 1_000_000, int $maxUncompressedSize = 1_000_000, int $maxRows = 1000, int $maxColumns = 100): ExtractLimitsDto
    {
        return new ExtractLimitsDto($maxFileSize, $maxUncompressedSize, $maxRows, $maxColumns);
    }
}

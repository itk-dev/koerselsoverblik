<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Extract;

use App\Import\Extract\ExtractProblem;
use App\Import\Extract\InvalidExtractException;
use App\Import\Extract\MissingColumnsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the codes of the problems that stop the read of an extract. The upload page translates
 * the code, and the command and the logs show the English message.
 */
final class ExtractProblemTest extends TestCase
{
    #[DataProvider('messages')]
    public function testExceptionHasTheProblemTheLimitAndTheMessage(ExtractProblem $problem, ?int $limit, string $message): void
    {
        $exception = new InvalidExtractException($problem, $limit);

        self::assertSame($problem, $exception->problem);
        self::assertSame($limit, $exception->limit);
        self::assertSame($message, $exception->getMessage());
    }

    /**
     * @return iterable<string, array{ExtractProblem, ?int, string}>
     */
    public static function messages(): iterable
    {
        yield 'not a file' => [ExtractProblem::NotFile, null, 'The extract is not a file.'];
        yield 'too large' => [ExtractProblem::TooLarge, 100, 'The extract is larger than 100 bytes.'];
        yield 'not xlsx' => [ExtractProblem::NotXlsx, null, 'The extract is not an xlsx file.'];
        yield 'too many files' => [ExtractProblem::TooManyFiles, 1000, 'The extract has more than 1000 files.'];
        yield 'unreadable' => [ExtractProblem::Unreadable, null, 'The extract has a file that cannot be read.'];
        yield 'too large uncompressed' => [ExtractProblem::TooLargeUncompressed, 200, 'The extract is larger than 200 bytes when it is not compressed.'];
        yield 'doctype' => [ExtractProblem::Doctype, null, 'The extract has a DOCTYPE.'];
        yield 'too many rows' => [ExtractProblem::TooManyRows, 50000, 'The extract has more than 50000 rows.'];
        yield 'invalid xml' => [ExtractProblem::InvalidXml, null, 'The extract has an XML file that is not valid.'];
        yield 'too many columns' => [ExtractProblem::TooManyColumns, 200, 'The extract has more than 200 columns.'];
        yield 'invalid cell reference' => [ExtractProblem::InvalidCellReference, null, 'The extract has a cell reference that is not valid.'];
        yield 'not a valid workbook' => [ExtractProblem::InvalidWorkbook, null, 'The extract is not a valid xlsx file.'];
        yield 'no header row' => [ExtractProblem::NoHeaderRow, 3, 'The extract has no header row 3.'];
        yield 'no sheet' => [ExtractProblem::NoSheet, 2, 'The extract has no sheet with the index 2.'];
    }

    public function testMissingColumnsHaveTheirProblem(): void
    {
        $exception = new MissingColumnsException(['RouteName', 'Date']);

        self::assertSame(ExtractProblem::MissingColumns, $exception->problem);
        self::assertNull($exception->limit);
        self::assertSame('The extract has no columns "RouteName", "Date".', $exception->getMessage());
        self::assertSame(['RouteName', 'Date'], $exception->columns);
    }
}

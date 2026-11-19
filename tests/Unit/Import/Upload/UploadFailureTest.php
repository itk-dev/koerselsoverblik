<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Upload;

use App\Import\Extract\ExtractProblem;
use App\Import\Extract\InvalidExtractException;
use App\Import\Extract\MissingColumnsException;
use App\Import\InvalidImportRangeException;
use App\Import\NoStopRowsException;
use App\Import\RowIdConflictException;
use App\Import\Upload\UploadFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UploadFailureTest extends TestCase
{
    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('failureProvider')]
    public function testGivesTheMessageOfTheFailure(InvalidExtractException|NoStopRowsException|RowIdConflictException|InvalidImportRangeException $exception, string $key, array $parameters): void
    {
        $message = UploadFailure::message($exception, 7);

        self::assertSame($key, $message->getMessage());
        self::assertSame($parameters, $message->getParameters());
    }

    /**
     * @return iterable<string, array{InvalidExtractException|NoStopRowsException|RowIdConflictException|InvalidImportRangeException, string, array<string, mixed>}>
     */
    public static function failureProvider(): iterable
    {
        yield 'missing columns' => [new MissingColumnsException(['a', 'b']), 'upload.missing_columns', ['columns' => 'a, b']];
        yield 'a problem with a limit' => [new InvalidExtractException(ExtractProblem::TooLarge, 10), 'upload.problem.too_large', ['limit' => 10]];
        yield 'a problem with no limit' => [new InvalidExtractException(ExtractProblem::NotXlsx), 'upload.problem.not_xlsx', ['limit' => 0]];
        yield 'no stop rows' => [new NoStopRowsException(), 'upload.no_stop_rows', []];
        yield 'row id conflict' => [new RowIdConflictException(), 'upload.row_id_conflict', []];
        yield 'too many days' => [new InvalidImportRangeException('The range has more than 7 days.'), 'upload.bulk.too_many_days', ['limit' => 7]];
    }
}

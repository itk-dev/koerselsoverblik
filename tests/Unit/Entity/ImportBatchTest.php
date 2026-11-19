<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ImportBatch;
use App\Entity\User;
use App\Import\Extract\RejectionCountDto;
use App\Import\Extract\RejectionReason;
use App\Import\ImportRangeDto;
use App\Import\ImportResultDto;
use App\Import\Mapping\FieldName;
use PHPUnit\Framework\TestCase;

/**
 * Checks that an import batch holds who, when, the range and the counts, and never a value from
 * the extract.
 */
final class ImportBatchTest extends TestCase
{
    public function testNewBatchHasTheUploadAndNoCounts(): void
    {
        $user = new User('admin@example.com');
        $uploadedAt = new \DateTimeImmutable('2026-01-07 10:15:00');

        $batch = new ImportBatch('default', 'extract.xlsx', $user, $uploadedAt, self::range());

        self::assertNull($batch->getId());
        self::assertSame('default', $batch->getMappingName());
        self::assertSame('extract.xlsx', $batch->getFileName());
        self::assertSame($user, $batch->getUploadedBy());
        self::assertSame($uploadedAt, $batch->getUploadedAt());
        self::assertSame('2026-01-05', $batch->getFirstDate()->format('Y-m-d'));
        self::assertSame('2026-01-06', $batch->getLastDate()->format('Y-m-d'));
        self::assertSame(0, $batch->getRowsDeleted());
        self::assertSame(0, $batch->getRowsInserted());
        self::assertSame(0, $batch->getRowsRejected());
        self::assertSame(0, $batch->getWarningCount());
        self::assertSame([], $batch->getErrors());
        self::assertSame([], $batch->getWarnings());
    }

    /**
     * The console has no user.
     */
    public function testBatchFromTheConsoleHasNoUser(): void
    {
        $batch = new ImportBatch('default', 'extract.xlsx', null, new \DateTimeImmutable(), self::range());

        self::assertNull($batch->getUploadedBy());
    }

    /**
     * The errors and the warnings are the groups of field and reason, with no values.
     */
    public function testCompleteKeepsTheCountsAndTheGroups(): void
    {
        $batch = new ImportBatch('default', 'extract.xlsx', null, new \DateTimeImmutable(), self::range());

        $batch->complete(new ImportResultDto(
            deletedRows: 12,
            insertedRows: 14,
            rejectedRows: 3,
            rejections: [
                new RejectionCountDto(FieldName::Run, RejectionReason::NotWholeNumber, 2, [4, 7]),
                new RejectionCountDto(null, RejectionReason::DuplicateRowId, 1, [9]),
            ],
            warningCount: 1,
            warnings: [new RejectionCountDto(FieldName::PlannedSolo, RejectionReason::UnknownBoolValue, 1, [5])],
            emptyDates: ['2026-01-06'],
        ));

        self::assertSame(12, $batch->getRowsDeleted());
        self::assertSame(14, $batch->getRowsInserted());
        self::assertSame(3, $batch->getRowsRejected());
        self::assertSame(1, $batch->getWarningCount());
        self::assertSame([
            ['field' => 'run', 'reason' => 'not_whole_number', 'count' => 2, 'row_numbers' => [4, 7]],
            ['field' => null, 'reason' => 'duplicate_row_id', 'count' => 1, 'row_numbers' => [9]],
        ], $batch->getErrors());
        self::assertSame([
            ['field' => 'planned_solo', 'reason' => 'unknown_bool_value', 'count' => 1, 'row_numbers' => [5]],
        ], $batch->getWarnings());
    }

    private static function range(): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-06'), 31);
    }
}

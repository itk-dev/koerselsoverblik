<?php

namespace App\Tests\Unit\Entity;

use App\Entity\ImportBatch;
use App\Entity\RejectedStopRow;
use App\Import\Extract\RejectedRowDto;
use App\Import\Extract\RejectionReason;
use App\Import\ImportRangeDto;
use App\Import\Mapping\FieldName;
use PHPUnit\Framework\TestCase;

/**
 * Checks that a stored rejected row has the reason and the copy, so that a later read through a
 * corrected mapping can import it.
 */
final class RejectedStopRowTest extends TestCase
{
    public function testRejectedRowHasTheReasonAndTheCopy(): void
    {
        $batch = self::batch();
        $row = new RejectedRowDto(7, RejectionReason::NotWholeNumber, FieldName::Run, new \DateTimeImmutable('2026-01-05'), ['blocknumber' => 'abc']);

        $rejected = new RejectedStopRow($row, $batch);

        self::assertNull($rejected->getId());
        self::assertSame($batch, $rejected->getImportBatch());
        self::assertSame(7, $rejected->getRowNumber());
        self::assertSame(RejectionReason::NotWholeNumber, $rejected->getReason());
        self::assertSame(FieldName::Run, $rejected->getField());
        self::assertSame($row->serviceDate, $rejected->getServiceDate());
        self::assertSame(['blocknumber' => 'abc'], $rejected->getSourceValues());
    }

    /**
     * A row with an invalid date field has no service date. A row id problem has no field.
     */
    public function testRejectedRowCanHaveNoFieldAndNoDate(): void
    {
        $rejected = new RejectedStopRow(new RejectedRowDto(8, RejectionReason::InvalidRowId, null), self::batch());

        self::assertNull($rejected->getField());
        self::assertNull($rejected->getServiceDate());
        self::assertSame([], $rejected->getSourceValues());
    }

    private static function batch(): ImportBatch
    {
        return new ImportBatch('default', 'extract.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 31));
    }
}

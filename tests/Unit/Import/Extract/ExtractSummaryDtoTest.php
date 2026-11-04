<?php

namespace App\Tests\Unit\Import\Extract;

use App\Import\Extract\ExtractSummaryDto;
use App\Import\Extract\RejectedRowDto;
use App\Import\Extract\RejectionCountDto;
use App\Import\Extract\RejectionReason;
use App\Import\Extract\StopRowDto;
use App\Import\Mapping\FieldName;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the summary counts the result of a parse for the validate command.
 */
final class ExtractSummaryDtoTest extends TestCase
{
    public function testCountsTheStopRowsAndTheRejectedRows(): void
    {
        $summary = ExtractSummaryDto::fromRows([
            self::stopRow(4),
            new RejectedRowDto(5, RejectionReason::Empty, FieldName::Route),
            self::stopRow(6),
            self::stopRow(7),
        ], 5);

        self::assertSame(3, $summary->stopRows);
        self::assertSame(1, $summary->rejectedRows);
    }

    /**
     * The rows with the same field and reason are in one group. The groups are in the order of
     * their first row.
     */
    public function testGroupsTheRejectionsByFieldAndReason(): void
    {
        $summary = ExtractSummaryDto::fromRows([
            new RejectedRowDto(5, RejectionReason::NotNumber, FieldName::Run),
            new RejectedRowDto(6, RejectionReason::Empty, FieldName::Route),
            new RejectedRowDto(7, RejectionReason::NotNumber, FieldName::Run),
            new RejectedRowDto(8, RejectionReason::InvalidRowId, null),
            new RejectedRowDto(9, RejectionReason::Empty, FieldName::PassengersAfter),
            new RejectedRowDto(10, RejectionReason::NotWholeNumber, FieldName::Run),
        ], 5);

        self::assertEquals(
            [
                new RejectionCountDto(FieldName::Run, RejectionReason::NotNumber, 2, [5, 7]),
                new RejectionCountDto(FieldName::Route, RejectionReason::Empty, 1, [6]),
                new RejectionCountDto(null, RejectionReason::InvalidRowId, 1, [8]),
                new RejectionCountDto(FieldName::PassengersAfter, RejectionReason::Empty, 1, [9]),
                new RejectionCountDto(FieldName::Run, RejectionReason::NotWholeNumber, 1, [10]),
            ],
            $summary->rejections,
        );
        self::assertSame(6, $summary->rejectedRows);
    }

    public function testGroupKeepsTheFirstTenRowNumbers(): void
    {
        $rows = array_map(static fn (int $rowNumber): RejectedRowDto => new RejectedRowDto($rowNumber, RejectionReason::Empty, FieldName::Route), range(2, 13));

        $summary = ExtractSummaryDto::fromRows($rows, 5);

        self::assertEquals([new RejectionCountDto(FieldName::Route, RejectionReason::Empty, 12, range(2, 11))], $summary->rejections);
    }

    public function testPreviewHasTheFirstStopRows(): void
    {
        $summary = ExtractSummaryDto::fromRows([
            new RejectedRowDto(3, RejectionReason::Empty, FieldName::Route),
            self::stopRow(4),
            self::stopRow(5),
            self::stopRow(6),
        ], 2);

        self::assertSame([4, 5], array_map(static fn (StopRowDto $row): int => $row->rowNumber, $summary->preview));
    }

    public function testDatesOfTheStopRowsAreSortedAndDifferent(): void
    {
        $summary = ExtractSummaryDto::fromRows([
            self::stopRow(4, '2026-01-07'),
            self::stopRow(5, '2026-01-05'),
            self::stopRow(6, '2026-01-09'),
            self::stopRow(7, '2026-01-05'),
            self::stopRow(8, '2026-01-06'),
        ], 5);

        self::assertSame(['2026-01-05', '2026-01-06', '2026-01-07', '2026-01-09'], $summary->dates);
    }

    public function testNoStopRowsGivesNoDates(): void
    {
        $summary = ExtractSummaryDto::fromRows([new RejectedRowDto(4, RejectionReason::Empty, FieldName::Route)], 5);

        self::assertSame([], $summary->dates);
        self::assertSame([], $summary->preview);
    }

    private static function stopRow(int $rowNumber, string $date = '2026-01-05'): StopRowDto
    {
        return StopRowDto::fromValues($rowNumber, null, [
            'route' => 'R-01',
            'person_id' => 'P-001',
            'service_date' => new \DateTimeImmutable($date),
            'run' => 1,
            'stop_type' => 'pickup',
            'stop_status' => 'completed',
            'passengers_after' => 1,
            'next_distance_km' => null,
            'next_duration_min' => null,
            'planned_time' => new \DateInterval('PT7H'),
            'actual_time' => null,
            'planned_solo' => null,
        ]);
    }
}

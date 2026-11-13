<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ImportBatch;
use App\Entity\Route;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use PHPUnit\Framework\TestCase;

/**
 * Checks that a stored stop row has the values and the copy of the parsed row.
 */
final class StopRowTest extends TestCase
{
    public function testStopRowHasTheValuesOfTheParsedRow(): void
    {
        $route = new Route('R-01', new SubAgreement()->setReference('R'));
        $batch = new ImportBatch('default', 'extract.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 31));
        $row = new StopRowDto(
            rowNumber: 4,
            rowKey: 'S-1',
            route: 'R-01',
            personId: 'P-001',
            serviceDate: new \DateTimeImmutable('2026-01-05'),
            run: 2,
            stopType: StopType::Pickup,
            stopStatus: StopStatus::Completed,
            passengersAfter: 1,
            nextDistanceKm: 3.5,
            nextDurationMin: 7.0,
            plannedTime: new \DateTimeImmutable('2026-01-05 07:10'),
            actualTime: new \DateTimeImmutable('2026-01-05 07:12'),
            plannedSolo: false,
            sourceValues: ['RouteName' => 'R-01', 'Note' => 'x'],
        );

        $stopRow = new StopRow($row, 'default', $route, $batch);

        self::assertNull($stopRow->getId());
        self::assertSame('default', $stopRow->getMappingName());
        self::assertSame('S-1', $stopRow->getRowKey());
        self::assertSame($route, $stopRow->getRoute());
        self::assertSame($row->serviceDate, $stopRow->getServiceDate());
        self::assertSame(2, $stopRow->getRun());
        self::assertSame(StopType::Pickup, $stopRow->getStopType());
        self::assertSame(StopStatus::Completed, $stopRow->getStopStatus());
        self::assertSame(1, $stopRow->getPassengersAfter());
        self::assertSame(3.5, $stopRow->getNextDistanceKm());
        self::assertSame(7.0, $stopRow->getNextDurationMin());
        self::assertSame($row->plannedTime, $stopRow->getPlannedTime());
        self::assertSame($row->actualTime, $stopRow->getActualTime());
        self::assertSame(120, $stopRow->getDelaySeconds());
        self::assertFalse($stopRow->getPlannedSolo());
        self::assertSame('P-001', $stopRow->getPersonId());
        self::assertSame(['RouteName' => 'R-01', 'Note' => 'x'], $stopRow->getSourceRow()->getValues());
        self::assertSame($batch, $stopRow->getImportBatch());
    }
}

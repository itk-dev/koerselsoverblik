<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\Entity\ImportBatch;
use App\Entity\RejectedStopRow;
use App\Entity\Route;
use App\Entity\SourceRow;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Import\Extract\RejectedRowDto;
use App\Import\Extract\RejectionReason;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Import\StopRowRangeDeleter;
use App\Repository\RejectedStopRowRepository;
use App\Repository\SourceRowRepository;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the deleter deletes the stop rows and the rejected rows of a range with their
 * copies, and that the rows and the copies of other dates stay.
 */
final class StopRowRangeDeleterTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    public function testDeleteRangeDeletesTheRowsOfTheRangeWithTheirCopies(): void
    {
        $this->storeRows();

        $deleted = $this->deleter()->deleteRange(self::range('2026-01-05'));

        self::assertSame(1, $deleted);
        $kept = array_map(
            static fn (SourceRow $sourceRow): mixed => $sourceRow->getValues()['name'],
            $this->entityManager->getRepository(SourceRow::class)->findBy([], ['id' => 'ASC']),
        );
        self::assertSame(['stop row 2026-01-06', 'rejected row 2026-01-06'], $kept);
        self::assertSame(1, $this->entityManager->getRepository(StopRow::class)->count([]));
        self::assertSame(1, $this->entityManager->getRepository(RejectedStopRow::class)->count([]));
    }

    public function testDeleteRangeWithNoRowsDeletesNothing(): void
    {
        $this->storeRows();

        self::assertSame(0, $this->deleter()->deleteRange(self::range('2026-01-07')));
        self::assertSame(5, $this->entityManager->getRepository(SourceRow::class)->count([]));
    }

    /**
     * Stores a stop row and a rejected row on two dates, and a rejected row with no date of the
     * batch of the first date.
     */
    private function storeRows(): void
    {
        $firstBatch = self::batch('2026-01-05');
        $secondBatch = self::batch('2026-01-06');
        $subAgreement = new SubAgreement()->setReference('R');
        $route = new Route('R-01', $subAgreement);
        $entities = [$firstBatch, $secondBatch, $subAgreement, $route];
        foreach ([$firstBatch, $secondBatch] as $batch) {
            $date = $batch->getFirstDate();
            $entities[] = new StopRow(self::stopRowDto($date), 'default', $route, $batch);
            $entities[] = new RejectedStopRow(new RejectedRowDto(5, RejectionReason::InvalidRowId, null, $date, ['name' => 'rejected row '.$date->format('Y-m-d')]), $batch);
        }
        $entities[] = new RejectedStopRow(new RejectedRowDto(6, RejectionReason::InvalidRowId, null, null, ['name' => 'rejected row with no date']), $firstBatch);
        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function deleter(): StopRowRangeDeleter
    {
        $stopRows = self::getContainer()->get(StopRowRepository::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);
        $rejectedRows = self::getContainer()->get(RejectedStopRowRepository::class);
        self::assertInstanceOf(RejectedStopRowRepository::class, $rejectedRows);
        $sourceRows = self::getContainer()->get(SourceRowRepository::class);
        self::assertInstanceOf(SourceRowRepository::class, $sourceRows);

        return new StopRowRangeDeleter($stopRows, $rejectedRows, $sourceRows);
    }

    private static function stopRowDto(\DateTimeImmutable $date): StopRowDto
    {
        return new StopRowDto(
            rowNumber: 4,
            rowKey: 'S-'.$date->format('Ymd'),
            route: 'R-01',
            personId: null,
            serviceDate: $date,
            run: 1,
            stopType: StopType::Pickup,
            stopStatus: StopStatus::Completed,
            passengersAfter: null,
            nextDistanceKm: null,
            nextDurationMin: null,
            plannedTime: $date->setTime(7, 10),
            actualTime: null,
            plannedSolo: null,
            sourceValues: ['name' => 'stop row '.$date->format('Y-m-d')],
        );
    }

    private static function batch(string $date): ImportBatch
    {
        return new ImportBatch('default', 'extract.xlsx', null, new \DateTimeImmutable(), self::range($date));
    }

    private static function range(string $date): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable($date), new \DateTimeImmutable($date), 31);
    }
}

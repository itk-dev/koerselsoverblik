<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

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
use App\Repository\SourceRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the replacer can delete the copies whose row it deleted, and that the copies of the
 * stored rows stay.
 */
final class SourceRowRepositoryTest extends KernelTestCase
{
    public function testDeleteOrphansDeletesOnlyTheCopiesWithNoRow(): void
    {
        $entityManager = $this->entityManager();
        $date = new \DateTimeImmutable('2026-01-05');
        $batch = new ImportBatch('default', 'extract.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create($date, $date, 31));
        $subAgreement = new SubAgreement()->setReference('R');
        $route = new Route('R-01', $subAgreement);
        $stopRow = new StopRow(self::stopRowDto($date), 'default', $route, $batch);
        $rejected = new RejectedStopRow(new RejectedRowDto(5, RejectionReason::InvalidRowId, null, $date, ['RouteStopID' => '']), $batch);
        foreach ([$batch, $subAgreement, $route, $stopRow, $rejected, new SourceRow(['a' => 1]), new SourceRow(['b' => 2])] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $entityManager->clear();

        $deleted = $this->repository()->deleteOrphans();

        self::assertSame(2, $deleted);
        $kept = array_map(
            static fn (SourceRow $sourceRow): array => $sourceRow->getValues(),
            $this->repository()->findBy([], ['id' => 'ASC']),
        );
        self::assertSame([['RouteName' => 'R-01'], ['RouteStopID' => '']], $kept);
    }

    public function testDeleteOrphansWithNoOrphansDeletesNothing(): void
    {
        self::assertSame(0, $this->repository()->deleteOrphans());
    }

    private static function stopRowDto(\DateTimeImmutable $date): StopRowDto
    {
        return new StopRowDto(
            rowNumber: 4,
            rowKey: 'S-1',
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
            sourceValues: ['RouteName' => 'R-01'],
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function repository(): SourceRowRepository
    {
        $repository = static::getContainer()->get(SourceRowRepository::class);
        self::assertInstanceOf(SourceRowRepository::class, $repository);

        return $repository;
    }
}

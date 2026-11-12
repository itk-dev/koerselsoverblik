<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\ImportBatch;
use App\Entity\Route;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the queries of the stop rows of a range, which the import and the clear of a date use.
 */
final class StopRowRepositoryTest extends KernelTestCase
{
    public function testSourceRowIdsInRangeGivesTheCopiesOfTheRowsInTheRange(): void
    {
        $copies = $this->storeRows();

        $ids = $this->repository()->sourceRowIdsInRange(self::range());

        sort($ids);
        self::assertSame([$copies['2026-01-05'], $copies['2026-01-06']], $ids);
    }

    public function testSourceRowIdsInRangeWithNoRowsIsEmpty(): void
    {
        self::assertSame([], $this->repository()->sourceRowIdsInRange(self::range()));
    }

    public function testDeleteInRangeDeletesOnlyTheRowsInTheRange(): void
    {
        $this->storeRows();

        $deleted = $this->repository()->deleteInRange(self::range());

        self::assertSame(2, $deleted);
        $kept = array_map(
            static fn (StopRow $row): string => $row->getServiceDate()->format('Y-m-d'),
            $this->repository()->findBy([], ['id' => 'ASC']),
        );
        self::assertSame(['2026-01-04', '2026-01-07'], $kept);
    }

    public function testDeleteInRangeWithNoRowsDeletesNothing(): void
    {
        self::assertSame(0, $this->repository()->deleteInRange(self::range()));
    }

    /**
     * Stores one stop row on each date from the day before the range to the day after the range.
     *
     * @return array<string, int> the ids of the copies, keyed by the date of the row as Y-m-d
     */
    private function storeRows(): array
    {
        $entityManager = $this->entityManager();
        $batch = new ImportBatch('default', 'extract.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create(new \DateTimeImmutable('2026-01-04'), new \DateTimeImmutable('2026-01-07'), 31));
        $subAgreement = new SubAgreement()->setReference('R');
        $route = new Route('R-01', $subAgreement);
        $rows = [];
        foreach (['2026-01-04', '2026-01-05', '2026-01-06', '2026-01-07'] as $date) {
            $rows[$date] = new StopRow(self::stopRowDto(new \DateTimeImmutable($date)), 'default', $route, $batch);
        }
        foreach ([$batch, $subAgreement, $route, ...array_values($rows)] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $copies = array_map(static fn (StopRow $row): int => (int) $row->getSourceRow()->getId(), $rows);
        $entityManager->clear();

        return $copies;
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
            passengers: null,
            pricePerMinute: null,
            cancellationNoticeMin: null,
            cancellationInTime: null,
            locationName: null,
            sourceValues: ['RouteName' => 'R-01'],
        );
    }

    private static function range(): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-06'), 31);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function repository(): StopRowRepository
    {
        $repository = static::getContainer()->get(StopRowRepository::class);
        self::assertInstanceOf(StopRowRepository::class, $repository);

        return $repository;
    }
}

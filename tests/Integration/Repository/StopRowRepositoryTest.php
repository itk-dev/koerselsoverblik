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
use App\Report\DateRangeDto;
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
     * The upload page shows the number of stop rows of each date in a month. A date with no stop
     * rows is not in the result.
     */
    public function testCountByDateGivesTheDatesInTheRangeInTheOrderOfTheDate(): void
    {
        $this->storeRows(['2026-01-06']);

        $counts = $this->repository()->countByDate(DateRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-06')));

        self::assertSame(['2026-01-05' => 1, '2026-01-06' => 2], $counts);
    }

    public function testCountByDateWithNoRowsIsEmpty(): void
    {
        self::assertSame([], $this->repository()->countByDate(DateRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-06'))));
    }

    /**
     * The driving data page lists the rows in the range in the order of the date, the route, the
     * run and the planned time.
     */
    public function testListInRangeGivesTheRowsInTheRangeInOrder(): void
    {
        $this->storeListRows();

        self::assertSame(
            ['A-01 1 07:00 05', 'A-01 1 08:00 05', 'A-01 2 06:00 05', 'B-01 1 06:00 05', 'A-01 1 07:00 06'],
            $this->listed(null),
        );
    }

    /**
     * MariaDB compares text with no regard to case.
     */
    public function testListInRangeWithARouteGivesOnlyTheRowsOfTheRoute(): void
    {
        $this->storeListRows();

        self::assertSame(['B-01 1 06:00 05'], $this->listed('b-01'));
        self::assertSame([], $this->listed('C-01'));
    }

    /**
     * The route B-01 is of an ignored sub-agreement. The count has the rows in the range only.
     */
    public function testCountIgnoredInRangeCountsTheRowsOfIgnoredRoutes(): void
    {
        $this->storeListRows();

        self::assertSame(1, $this->repository()->countIgnoredInRange(self::range(), null));
        self::assertSame(1, $this->repository()->countIgnoredInRange(self::range(), 'b-01'));
        self::assertSame(0, $this->repository()->countIgnoredInRange(self::range(), 'A-01'));
    }

    public function testCountIgnoredByDateGivesTheDatesWithRowsOfIgnoredRoutes(): void
    {
        $this->storeListRows();

        $counts = $this->repository()->countIgnoredByDate(DateRangeDto::create(new \DateTimeImmutable('2026-01-04'), new \DateTimeImmutable('2026-01-07')));

        self::assertSame(['2026-01-05' => 1], $counts);
    }

    /**
     * With no ignored sub-agreement, the counts are zero.
     */
    public function testNoIgnoredRoutesCountsZero(): void
    {
        $this->storeRows();

        self::assertSame(0, $this->repository()->countIgnoredInRange(self::range(), null));
        self::assertSame([], $this->repository()->countIgnoredByDate(DateRangeDto::create(new \DateTimeImmutable('2026-01-04'), new \DateTimeImmutable('2026-01-07'))));
    }

    /**
     * The list reads the sub-agreement of each row in the same query, so the page can mark the
     * rows of an ignored sub-agreement.
     */
    public function testListInRangeReadsTheSubAgreement(): void
    {
        $this->storeListRows();

        /** @var list<StopRow> $rows */
        $rows = $this->repository()->listInRange(self::range(), 'B-01')->getResult();

        self::assertTrue($rows[0]->getRoute()->getSubAgreement()->isIgnored());
        self::assertFalse($this->entityManager()->getUnitOfWork()->isUninitializedObject($rows[0]->getRoute()->getSubAgreement()));
    }

    /**
     * Stores rows of two routes on the dates of the range and on the day before and after it. The
     * rows are stored in another order than the order of the list. B-01 is of an ignored
     * sub-agreement.
     */
    private function storeListRows(): void
    {
        $entityManager = $this->entityManager();
        $batch = new ImportBatch('default', 'extract.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create(new \DateTimeImmutable('2026-01-04'), new \DateTimeImmutable('2026-01-07'), 31));
        $subAgreement = new SubAgreement()->setReference('A');
        $ignored = new SubAgreement()->setReference('B')->setIgnored(true);
        $routes = ['A-01' => new Route('A-01', $subAgreement), 'B-01' => new Route('B-01', $ignored)];
        $entities = [$batch, $subAgreement, $ignored, ...array_values($routes)];
        foreach ([
            ['2026-01-06', 'A-01', 1, 7],
            ['2026-01-05', 'B-01', 1, 6],
            ['2026-01-05', 'A-01', 2, 6],
            ['2026-01-05', 'A-01', 1, 8],
            ['2026-01-05', 'A-01', 1, 7],
            ['2026-01-04', 'A-01', 1, 7],
            ['2026-01-07', 'A-01', 1, 7],
        ] as $index => [$date, $route, $run, $hour]) {
            $day = new \DateTimeImmutable($date);
            $dto = new StopRowDto(
                rowNumber: $index + 2,
                rowKey: 'L-'.$index,
                route: $route,
                personId: null,
                serviceDate: $day,
                run: $run,
                stopType: StopType::Pickup,
                stopStatus: StopStatus::Completed,
                passengersAfter: null,
                nextDistanceKm: null,
                nextDurationMin: null,
                plannedTime: $day->setTime($hour, 0),
                actualTime: null,
                plannedSolo: null,
                locationName: null,
                sourceValues: [],
            );
            $entities[] = new StopRow($dto, 'default', $routes[$route], $batch);
        }
        foreach ($entities as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $entityManager->clear();
    }

    /**
     * @return list<string> each row as the route, the run, the planned time and the day
     */
    private function listed(?string $route): array
    {
        /** @var list<StopRow> $rows */
        $rows = $this->repository()->listInRange(self::range(), $route)->getResult();

        return array_map(
            static fn (StopRow $row): string => \sprintf('%s %d %s', $row->getRoute()->getSourceKey(), $row->getRun(), $row->getPlannedTime()->format('H:i d')),
            $rows,
        );
    }

    /**
     * Stores one stop row on each date from the day before the range to the day after the range.
     *
     * @param list<string> $extraDates the dates of more stop rows, as Y-m-d
     *
     * @return array<string, int> the ids of the copies, keyed by the date of the row as Y-m-d
     */
    private function storeRows(array $extraDates = []): array
    {
        $entityManager = $this->entityManager();
        $batch = new ImportBatch('default', 'extract.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create(new \DateTimeImmutable('2026-01-04'), new \DateTimeImmutable('2026-01-07'), 31));
        $subAgreement = new SubAgreement()->setReference('R');
        $route = new Route('R-01', $subAgreement);
        $rows = [];
        foreach (['2026-01-04', '2026-01-05', '2026-01-06', '2026-01-07'] as $date) {
            $rows[$date] = new StopRow(self::stopRowDto(new \DateTimeImmutable($date)), 'default', $route, $batch);
        }
        $extraRows = [];
        foreach ($extraDates as $index => $date) {
            $extraRows[] = new StopRow(self::stopRowDto(new \DateTimeImmutable($date), 'E'.$index), 'default', $route, $batch);
        }
        foreach ([$batch, $subAgreement, $route, ...array_values($rows), ...$extraRows] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $copies = array_map(static fn (StopRow $row): int => (int) $row->getSourceRow()->getId(), $rows);
        $entityManager->clear();

        return $copies;
    }

    private static function stopRowDto(\DateTimeImmutable $date, string $suffix = ''): StopRowDto
    {
        return new StopRowDto(
            rowNumber: 4,
            rowKey: 'S-'.$date->format('Ymd').$suffix,
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

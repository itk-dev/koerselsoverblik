<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Route;
use App\Entity\RouteRun;
use App\Entity\SubAgreement;
use App\Import\ImportRangeDto;
use App\Import\RouteRunDto;
use App\Report\DateRangeDto;
use App\Repository\RouteRunRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the counts of the route runs, which the upload page and the clear of a date use.
 */
final class RouteRunRepositoryTest extends KernelTestCase
{
    public function testCountInRangeCountsOnlyTheRunsInTheRange(): void
    {
        $this->storeRuns();

        self::assertSame(3, $this->repository()->countInRange(ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-06'), 31)));
    }

    public function testDeleteInRangeGivesTheNumberOfDeletedRuns(): void
    {
        $this->storeRuns();

        $deleted = $this->repository()->deleteInRange(ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-06'), 31));

        self::assertSame(3, $deleted);
        self::assertSame(2, $this->repository()->count([]));
    }

    public function testCountInRangeWithNoRunsIsZero(): void
    {
        self::assertSame(0, $this->repository()->countInRange(ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-06'), 31)));
    }

    /**
     * A date with no runs is not in the result.
     */
    public function testCountByDateGivesTheDatesInTheRangeInTheOrderOfTheDate(): void
    {
        $this->storeRuns();

        $counts = $this->repository()->countByDate(DateRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-06')));

        self::assertSame(['2026-01-05' => 1, '2026-01-06' => 2], $counts);
    }

    public function testCountByDateWithNoRunsIsEmpty(): void
    {
        self::assertSame([], $this->repository()->countByDate(DateRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-06'))));
    }

    /**
     * Stores one run on the day before the range, one run on the first date, two runs on the last
     * date and one run on the day after the range. The runs are stored in an order that is not the
     * order of the date.
     */
    private function storeRuns(): void
    {
        $entityManager = $this->entityManager();
        $subAgreement = new SubAgreement()->setReference('R');
        $route = new Route('R-01', $subAgreement);
        $entityManager->persist($subAgreement);
        $entityManager->persist($route);
        foreach ([['2026-01-07', 1], ['2026-01-06', 1], ['2026-01-05', 1], ['2026-01-06', 2], ['2026-01-04', 1]] as [$date, $run]) {
            $entityManager->persist(new RouteRun($route, new \DateTimeImmutable($date), $run, new RouteRunDto(null, null, null, null, null, null, false, null)));
        }
        $entityManager->flush();
        $entityManager->clear();
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function repository(): RouteRunRepository
    {
        $repository = static::getContainer()->get(RouteRunRepository::class);
        self::assertInstanceOf(RouteRunRepository::class, $repository);

        return $repository;
    }
}

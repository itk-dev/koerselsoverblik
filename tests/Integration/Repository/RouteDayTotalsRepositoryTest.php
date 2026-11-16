<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Route;
use App\Entity\RouteDayTotals;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Report\DateRangeDto;
use App\Report\DayTotalsDto;
use App\Repository\OtherToleranceException;
use App\Repository\RouteDayTotalsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the sums of the totals of each route and day. The test environment has a tolerance of 5
 * minutes.
 */
final class RouteDayTotalsRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    /**
     * The sums of a range, of each month and of each route add the rows of the range only.
     */
    public function testSumsAddTheRowsOfTheRange(): void
    {
        $first = $this->route('A');
        $second = $this->route('B');
        $this->totals($first, '2026-01-31', 2);
        $this->totals($second, '2026-01-31', 3);
        $this->totals($first, '2026-02-01', 4);
        $this->totals($first, '2026-03-01', 100);

        $range = self::range('2026-01-01', '2026-02-28');

        self::assertSame(9, $this->repository()->sum($range)->trips);
        self::assertSame(['2026-01' => 5, '2026-02' => 4], array_map(static fn (DayTotalsDto $totals): int => $totals->trips, $this->repository()->sumByMonth($range)));
        self::assertSame([(int) $first->getId() => 6, (int) $second->getId() => 3], array_map(static fn (DayTotalsDto $totals): int => $totals->trips, $this->repository()->sumOfEachRoute($range)));
    }

    /**
     * The ignored flag and the supplier of a sub-agreement apply in the query, so a change needs
     * no rebuild of the totals.
     */
    public function testChangeOfASubAgreementNeedsNoRebuild(): void
    {
        $supplier = new Supplier()->setName('Vognmand A');
        $this->entityManager->persist($supplier);
        $ofSupplier = $this->route('A', $supplier);
        $other = $this->route('B');
        $this->totals($ofSupplier, '2026-01-05', 2);
        $this->totals($other, '2026-01-05', 3);
        $range = self::range('2026-01-01', '2026-01-31');

        self::assertSame(5, $this->repository()->sum($range)->trips);
        self::assertSame(2, $this->repository()->sum($range, $supplier->getId())->trips);

        $other->getSubAgreement()->setIgnored(true);
        $ofSupplier->getSubAgreement()->setSupplier(null);

        self::assertSame(2, $this->repository()->sum($range)->trips);
        self::assertSame(0, $this->repository()->sum($range, $supplier->getId())->trips);
    }

    /**
     * A row that was built with another tolerance has wrong counts on time, so the sums fail with
     * a message that tells how to build the rows again.
     */
    public function testRowWithAnotherToleranceStopsTheSums(): void
    {
        $this->totals($this->route('A'), '2026-01-05', 2, toleranceMinutes: 10);
        $range = self::range('2026-01-01', '2026-01-31');

        foreach ([
            fn () => $this->repository()->sum($range),
            fn () => $this->repository()->sumByMonth($range),
            fn () => $this->repository()->sumOfEachRoute($range),
        ] as $sum) {
            try {
                $sum();
                self::fail('The sum did not fail.');
            } catch (OtherToleranceException $exception) {
                self::assertStringContainsString('app:driving-data:rebuild-totals', $exception->getMessage());
            }
        }
    }

    public function testDeleteAllDeletesAllRows(): void
    {
        $this->totals($this->route('A'), '2026-01-05', 2);
        $this->totals($this->route('B'), '2027-06-01', 3);

        $this->repository()->deleteAll();

        self::assertSame(0, $this->repository()->count());
    }

    private function repository(): RouteDayTotalsRepository
    {
        $this->entityManager->flush();
        $totals = $this->entityManager->getRepository(RouteDayTotals::class);
        self::assertInstanceOf(RouteDayTotalsRepository::class, $totals);

        return $totals;
    }

    private function route(string $reference, ?Supplier $supplier = null): Route
    {
        $subAgreement = new SubAgreement()->setReference($reference)->setSupplier($supplier);
        $this->entityManager->persist($subAgreement);
        $route = new Route($reference.'-01', $subAgreement);
        $this->entityManager->persist($route);
        $this->entityManager->flush();

        return $route;
    }

    /**
     * Stores the totals of a route and a day with only trips.
     */
    private function totals(Route $route, string $date, int $trips, int $toleranceMinutes = 5): void
    {
        $totals = DayTotalsDto::fromRow(['trips' => $trips], ['trips']);
        $this->entityManager->persist(new RouteDayTotals($route, new \DateTimeImmutable($date), $toleranceMinutes, $totals));
    }

    private static function range(string $firstDate, string $lastDate): DateRangeDto
    {
        return DateRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate));
    }
}

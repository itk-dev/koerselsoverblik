<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\LocationDayTotals;
use App\Entity\Route;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Report\DateRangeDto;
use App\Report\LocationPunctualityDto;
use App\Report\PunctualityCountDto;
use App\Repository\LocationDayTotalsRepository;
use App\Repository\OtherToleranceException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the sums of the measured stops of each location. The test environment has a tolerance of
 * 5 minutes.
 */
final class LocationDayTotalsRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    /**
     * The sum of a location adds the rows of all routes and dates of the range, and only of the
     * range.
     */
    public function testSumAddsTheRoutesAndTheDatesOfTheRange(): void
    {
        $first = $this->route('A');
        $second = $this->route('B');
        $this->totals($first, '2025-12-31', 'Skole 1', 100, 100);
        $this->totals($first, '2026-01-01', 'Skole 1', 1, 2);
        $this->totals($second, '2026-01-01', 'Skole 1', 3, 4);
        $this->totals($first, '2026-01-31', 'Skole 1', 5, 6);
        $this->totals($first, '2026-01-31', 'Skole 2', 7, 8);
        $this->totals($first, '2026-02-01', 'Skole 2', 100, 100);

        self::assertEquals([
            'Skole 1' => new PunctualityCountDto(9, 12),
            'Skole 2' => new PunctualityCountDto(7, 8),
        ], $this->sums(self::range('2026-01-01', '2026-01-31')));
    }

    /**
     * A location name with only digits stays text.
     */
    public function testLocationNameWithOnlyDigitsIsText(): void
    {
        $this->totals($this->route('A'), '2026-01-05', '101', 1, 1);

        self::assertEquals([new LocationPunctualityDto('101', new PunctualityCountDto(1, 1))], $this->repository()->sumOfEachLocation(self::range('2026-01-01', '2026-01-31')));
    }

    /**
     * The ignored flag and the supplier of a sub-agreement apply in the query, so a change needs
     * no rebuild of the rows.
     */
    public function testChangeOfASubAgreementNeedsNoRebuild(): void
    {
        $supplier = new Supplier()->setName('Vognmand A');
        $this->entityManager->persist($supplier);
        $ofSupplier = $this->route('A', $supplier);
        $other = $this->route('B');
        $this->totals($ofSupplier, '2026-01-05', 'Skole 1', 1, 2);
        $this->totals($other, '2026-01-05', 'Skole 1', 3, 4);
        $this->totals($other, '2026-01-05', 'Skole 2', 5, 6);
        $range = self::range('2026-01-01', '2026-01-31');

        self::assertEquals(['Skole 1' => new PunctualityCountDto(4, 6), 'Skole 2' => new PunctualityCountDto(5, 6)], $this->sums($range));
        self::assertEquals(['Skole 1' => new PunctualityCountDto(1, 2)], $this->sums($range, $supplier->getId()));

        $other->getSubAgreement()->setIgnored(true);
        $ofSupplier->getSubAgreement()->setSupplier(null);

        self::assertEquals(['Skole 1' => new PunctualityCountDto(1, 2)], $this->sums($range));
        self::assertSame([], $this->sums($range, $supplier->getId()));
    }

    /**
     * A row that was built with another tolerance has wrong counts on time, so the sum fails with
     * a message that tells how to build the rows again. The message has no location name.
     */
    public function testRowWithAnotherToleranceStopsTheSum(): void
    {
        $this->totals($this->route('A'), '2026-01-05', 'Skole 1', 1, 1);
        $this->totals($this->route('B'), '2026-01-05', 'Skole 2', 1, 1, toleranceMinutes: 10);

        try {
            $this->repository()->sumOfEachLocation(self::range('2026-01-01', '2026-01-31'));
            self::fail('The sum did not fail.');
        } catch (OtherToleranceException $exception) {
            self::assertStringContainsString('app:driving-data:rebuild-totals', $exception->getMessage());
            self::assertStringNotContainsString('Skole', $exception->getMessage());
        }
    }

    public function testRangeWithNoRowsHasNoLocations(): void
    {
        self::assertSame([], $this->repository()->sumOfEachLocation(self::range('2026-01-01', '2026-01-31')));
    }

    public function testDeleteAllDeletesAllRows(): void
    {
        $this->totals($this->route('A'), '2026-01-05', 'Skole 1', 1, 1);
        $this->totals($this->route('B'), '2027-06-01', 'Skole 2', 1, 1);

        $this->repository()->deleteAll();

        self::assertSame(0, $this->repository()->count());
    }

    private function repository(): LocationDayTotalsRepository
    {
        $this->entityManager->flush();
        $totals = $this->entityManager->getRepository(LocationDayTotals::class);
        self::assertInstanceOf(LocationDayTotalsRepository::class, $totals);

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

    private function totals(Route $route, string $date, string $location, int $onTime, int $measured, int $toleranceMinutes = 5): void
    {
        $this->entityManager->persist(new LocationDayTotals($route, new \DateTimeImmutable($date), $location, $toleranceMinutes, new PunctualityCountDto($onTime, $measured)));
    }

    /**
     * @return array<string, PunctualityCountDto> keyed by the location name, in the order of the name
     */
    private function sums(DateRangeDto $range, ?int $supplierId = null): array
    {
        $sums = [];
        foreach ($this->repository()->sumOfEachLocation($range, $supplierId) as $location) {
            $sums[$location->location] = $location->stops;
        }
        ksort($sums);

        return $sums;
    }

    private static function range(string $firstDate, string $lastDate): DateRangeDto
    {
        return DateRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate));
    }
}

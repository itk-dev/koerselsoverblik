<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\Entity\ImportBatch;
use App\Entity\MonthCitizens;
use App\Entity\Route;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Import\MonthCitizensBuilder;
use App\Report\DateRangeDto;
use App\Repository\MonthCitizensRepository;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the citizens of each month are the same as the count on the stop rows, for all
 * suppliers and for each supplier, and that a change of a sub-agreement builds the counts that
 * it changes.
 */
final class MonthCitizensBuilderTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private int $rowKey = 0;

    private ?ImportBatch $batch = null;

    private Supplier $supplierA;

    private Supplier $supplierB;

    private SubAgreement $subAgreementA;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        // P-A rides with both suppliers, and P-D only on a route with no supplier.
        $this->supplierA = $this->supplier('Vognmand A');
        $this->supplierB = $this->supplier('Vognmand B');
        $this->subAgreementA = $this->subAgreement('A', $this->supplierA);
        $routeA = $this->route('A-01', $this->subAgreementA);
        $routeB = $this->route('B-01', $this->subAgreement('B', $this->supplierB));
        $unknown = $this->route('U-01', $this->subAgreement('U', null));
        $ignored = $this->route('I-01', $this->subAgreement('I', $this->supplierA)->setIgnored(true));
        $this->stop($routeA, '2026-01-05', 'P-A');
        $this->stop($routeA, '2026-01-20', 'P-B');
        $this->stop($routeB, '2026-01-31', 'P-A');
        $this->stop($routeB, '2026-02-01', 'P-C', StopStatus::Cancelled);
        $this->stop($unknown, '2026-02-02', 'P-D');
        $this->stop($ignored, '2026-02-03', 'P-E');
        $this->entityManager->flush();
    }

    /**
     * The counts are the same as the query on the stop rows: a cancelled pickup and an ignored
     * sub-agreement do not count, and a route with no supplier is in the count of all suppliers.
     */
    public function testCountsAreTheCountsOfTheStopRows(): void
    {
        $this->builder()->rebuild(self::range('2026-01-01', '2026-02-28'));

        foreach ([null, $this->supplierA->getId(), $this->supplierB->getId()] as $supplierId) {
            $expected = $this->stopRows()->countCitizensByMonth(self::range('2026-01-01', '2026-02-28'), $supplierId);
            ksort($expected);
            self::assertSame($expected, $this->citizens($supplierId));
        }
        self::assertSame(['2026-01' => 2, '2026-02' => 1], $this->citizens(null));
        self::assertSame(['2026-01' => 2], $this->citizens($this->supplierA->getId()));
    }

    /**
     * A range in the middle of a month builds the full months. A second build replaces the rows,
     * so a month has one row for all suppliers. A month outside the range does not change.
     */
    public function testRebuildReplacesTheFullMonthsOfTheRange(): void
    {
        $this->entityManager->persist(new MonthCitizens(new \DateTimeImmutable('2026-03-01'), null, 99));
        $this->entityManager->flush();

        $this->builder()->rebuild(self::range('2026-01-20', '2026-01-21'));
        $this->builder()->rebuild(self::range('2026-01-20', '2026-01-21'));

        self::assertSame(['2026-01' => 2, '2026-03' => 99], $this->citizens(null, self::range('2026-01-01', '2026-03-31')));
    }

    /**
     * The ignored flag changes the count of all suppliers and of the supplier of the
     * sub-agreement. The count of another supplier does not change.
     */
    public function testChangeOfTheIgnoredFlag(): void
    {
        $this->builder()->rebuild(self::range('2026-01-01', '2026-02-28'));
        $this->entityManager->persist(new MonthCitizens(new \DateTimeImmutable('2026-03-01'), $this->supplierB, 99));
        $this->subAgreementA->setIgnored(true);
        $this->entityManager->flush();

        $this->builder()->rebuildAfterSubAgreementChange(true, $this->supplierA->getId(), $this->supplierA->getId());

        self::assertSame(['2026-01' => 1, '2026-02' => 1], $this->citizens(null));
        self::assertSame([], $this->citizens($this->supplierA->getId()));
        self::assertSame(['2026-01' => 1, '2026-03' => 99], $this->citizens($this->supplierB->getId(), self::range('2026-01-01', '2026-03-31')));
    }

    /**
     * A new supplier changes the counts of the old and the new supplier, but not the count of all
     * suppliers.
     */
    public function testChangeOfTheSupplier(): void
    {
        $this->builder()->rebuild(self::range('2026-01-01', '2026-02-28'));
        $this->entityManager->persist(new MonthCitizens(new \DateTimeImmutable('2026-03-01'), null, 99));
        $this->subAgreementA->setSupplier($this->supplierB);
        $this->entityManager->flush();

        $this->builder()->rebuildAfterSubAgreementChange(false, $this->supplierA->getId(), $this->supplierB->getId());

        self::assertSame([], $this->citizens($this->supplierA->getId()));
        self::assertSame(['2026-01' => 2], $this->citizens($this->supplierB->getId()));
        self::assertSame(99, $this->citizens(null, self::range('2026-03-01', '2026-03-31'))['2026-03']);
    }

    /**
     * A sub-agreement that gets its first supplier changes only the count of that supplier. A save
     * with no change builds nothing.
     */
    public function testFirstSupplierAndNoChange(): void
    {
        $this->entityManager->persist(new MonthCitizens(new \DateTimeImmutable('2026-03-01'), null, 99));
        $this->entityManager->persist(new MonthCitizens(new \DateTimeImmutable('2026-03-01'), $this->supplierA, 98));
        $this->entityManager->flush();

        $this->builder()->rebuildAfterSubAgreementChange(false, $this->supplierA->getId(), $this->supplierA->getId());
        self::assertSame(['2026-03' => 98], $this->citizens($this->supplierA->getId(), self::range('2026-03-01', '2026-03-31')));

        $this->builder()->rebuildAfterSubAgreementChange(false, null, $this->supplierA->getId());
        self::assertSame(['2026-01' => 2], $this->citizens($this->supplierA->getId(), self::range('2026-01-01', '2026-03-31')));
        self::assertSame(['2026-03' => 99], $this->citizens(null, self::range('2026-03-01', '2026-03-31')));
    }

    /**
     * @return array<string, int>
     */
    private function citizens(?int $supplierId, ?DateRangeDto $range = null): array
    {
        $this->entityManager->clear();
        $months = $this->entityManager->getRepository(MonthCitizens::class);
        self::assertInstanceOf(MonthCitizensRepository::class, $months);
        $citizens = $months->byMonth($range ?? self::range('2026-01-01', '2026-02-28'), $supplierId);
        ksort($citizens);

        return $citizens;
    }

    private function builder(): MonthCitizensBuilder
    {
        $builder = self::getContainer()->get(MonthCitizensBuilder::class);
        self::assertInstanceOf(MonthCitizensBuilder::class, $builder);

        return $builder;
    }

    private function stopRows(): StopRowRepository
    {
        $stopRows = $this->entityManager->getRepository(StopRow::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);

        return $stopRows;
    }

    private static function range(string $firstDate, string $lastDate): DateRangeDto
    {
        return DateRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate));
    }

    private function supplier(string $name): Supplier
    {
        $supplier = new Supplier()->setName($name);
        $this->entityManager->persist($supplier);

        return $supplier;
    }

    private function subAgreement(string $reference, ?Supplier $supplier): SubAgreement
    {
        $subAgreement = new SubAgreement()->setReference($reference)->setSupplier($supplier);
        $this->entityManager->persist($subAgreement);

        return $subAgreement;
    }

    private function route(string $name, SubAgreement $subAgreement): Route
    {
        $route = new Route($name, $subAgreement);
        $this->entityManager->persist($route);

        return $route;
    }

    private function stop(Route $route, string $date, string $person, StopStatus $status = StopStatus::Completed): void
    {
        $serviceDate = new \DateTimeImmutable($date);
        $row = new StopRowDto(
            rowNumber: ++$this->rowKey,
            rowKey: 'S-'.$this->rowKey,
            route: $route->getSourceKey(),
            personId: $person,
            serviceDate: $serviceDate,
            run: 1,
            stopType: StopType::Pickup,
            stopStatus: $status,
            passengersAfter: null,
            nextDistanceKm: null,
            nextDurationMin: null,
            plannedTime: $serviceDate->setTime(7, 0),
            actualTime: null,
            plannedSolo: null,
            locationName: null,
        );
        $this->entityManager->persist(new StopRow($row, 'default', $route, $this->batch()));
    }

    private function batch(): ImportBatch
    {
        if (null === $this->batch) {
            $this->batch = new ImportBatch('default', 'test.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-03-31'), 90));
            $this->entityManager->persist($this->batch);
        }

        return $this->batch;
    }
}

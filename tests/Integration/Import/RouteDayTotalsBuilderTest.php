<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\Route;
use App\Entity\RouteDayTotals;
use App\Entity\SubAgreement;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Import\RouteDayTotalsBuilder;
use App\Report\DayTotalsDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the builder stores the counters of each route and day of a range. The base scenario
 * has the routes R-01 and R-02 of the sub-agreement "R", and one route of "BOOKED", on 5 January.
 */
final class RouteDayTotalsBuilderTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    /**
     * Each route and date has one row with the counters of the stops and of the runs, and the
     * tolerance of the build. The trips and the runs are in the "expected" section of the
     * scenario.
     */
    public function testEachRouteAndDateHasOneRow(): void
    {
        $this->importBase();

        $rows = $this->rows();

        self::assertCount(3, $rows);
        self::assertSame([5, 5, 5], array_map(static fn (RouteDayTotals $row): int => $row->getToleranceMinutes(), $rows));
        self::assertSame(['2026-01-05'], array_values(array_unique(array_map(static fn (RouteDayTotals $row): string => $row->getServiceDate()->format('Y-m-d'), $rows))));
        $sum = array_reduce($rows, static fn (DayTotalsDto $sum, RouteDayTotals $row): DayTotalsDto => $sum->add($row->getTotals()), DayTotalsDto::empty());
        self::assertSame([7, 1, 4, 2], [$sum->trips, $sum->cancelledTrips, $sum->runs, $sum->soloRuns]);
        self::assertGreaterThan(0, $sum->lastDropMeasured);
        self::assertGreaterThan(0, $sum->pickupMeasured);
    }

    /**
     * The table has the routes of an ignored sub-agreement too, because the queries apply the
     * flag. A change of the flag then needs no rebuild.
     */
    public function testIgnoredSubAgreementIsStored(): void
    {
        $this->importBase();
        $subAgreement = $this->entityManager->getRepository(SubAgreement::class)->findOneBy(['reference' => 'BOOKED']);
        self::assertInstanceOf(SubAgreement::class, $subAgreement);
        $subAgreement->setIgnored(true);
        $this->entityManager->flush();

        $this->builder()->rebuild(self::range('2026-01-05', '2026-01-05'));

        self::assertCount(3, $this->rows());
    }

    /**
     * A rebuild deletes and builds the rows of its range only. A date with no stop rows and no
     * runs gets no row.
     */
    public function testRebuildChangesOnlyItsRange(): void
    {
        $this->importBase();
        $route = $this->entityManager->getRepository(Route::class)->findOneBy([]);
        self::assertInstanceOf(Route::class, $route);
        $this->entityManager->persist(new RouteDayTotals($route, new \DateTimeImmutable('2026-01-06'), 5, DayTotalsDto::empty()));
        $this->entityManager->flush();

        self::assertSame(3, $this->builder()->rebuild(self::range('2026-01-05', '2026-01-05')));
        self::assertCount(4, $this->rows());

        self::assertSame(0, $this->builder()->rebuild(self::range('2026-01-06', '2026-01-06')));
        self::assertCount(3, $this->rows());
    }

    /**
     * @return list<RouteDayTotals>
     */
    private function rows(): array
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(RouteDayTotals::class)->findBy([], ['id' => 'ASC']);
    }

    private function builder(): RouteDayTotalsBuilder
    {
        $builder = self::getContainer()->get(RouteDayTotalsBuilder::class);
        self::assertInstanceOf(RouteDayTotalsBuilder::class, $builder);

        return $builder;
    }

    private static function range(string $firstDate, string $lastDate): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate), 31);
    }

    private function importBase(): void
    {
        $scenario = new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml');
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);
        new ExtractWriter()->write($path, $provider->default(), $scenario->rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $importer->import($path, self::range('2026-01-05', '2026-01-05'), $provider->default(), null, 'base.xlsx');
        unlink($path);
    }
}

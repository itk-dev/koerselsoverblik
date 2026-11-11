<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\ImportBatch;
use App\Entity\Route;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Import\DrivingDataImporter;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Report\DateRangeDto;
use App\Report\TripCountDto;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the trips and the citizens of a date range: a trip is a pickup, also a cancelled pickup
 * and a pickup with no registration.
 */
final class StopRowTripCountTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    private EntityManagerInterface $entityManager;

    private int $rowKey = 0;

    private ?ImportBatch $batch = null;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    /**
     * The trips are in the "expected" section of the scenario. The scenario has 7 person ids, but
     * P-006 has only a cancelled trip, so 6 citizens were driven.
     */
    public function testBaseScenario(): void
    {
        $this->importBase();

        self::assertEquals(
            new TripCountDto(trips: 7, completed: 5, cancelled: 1, pending: 1, citizens: 6),
            $this->trips('2026-01-01', '2026-01-31'),
        );
    }

    /**
     * A citizen who was not driven does not count. A pickup with no registration is not
     * cancelled, so its citizen counts.
     */
    public function testCitizenWithOnlyCancelledTripsDoesNotCount(): void
    {
        $route = $this->route();
        $this->stop($route, '2026-02-02', 'P-A', StopType::Pickup, StopStatus::Cancelled);
        $this->stop($route, '2026-02-03', 'P-A', StopType::Pickup, StopStatus::Cancelled);
        $this->stop($route, '2026-02-03', 'P-B', StopType::Pickup, StopStatus::Pending);

        self::assertEquals(
            new TripCountDto(trips: 3, completed: 0, cancelled: 2, pending: 1, citizens: 1),
            $this->trips('2026-02-01', '2026-02-28'),
        );
    }

    public function testCitizenWithTwoTripsCountsOnce(): void
    {
        $route = $this->route();
        $this->stop($route, '2026-02-02', 'P-A', StopType::Pickup, StopStatus::Completed);
        $this->stop($route, '2026-02-02', 'P-A', StopType::DropOff, StopStatus::Completed);
        $this->stop($route, '2026-02-03', 'P-A', StopType::Pickup, StopStatus::Cancelled);
        $this->stop($route, '2026-02-03', 'P-B', StopType::Pickup, StopStatus::Completed);

        self::assertEquals(
            new TripCountDto(trips: 3, completed: 2, cancelled: 1, pending: 0, citizens: 2),
            $this->trips('2026-02-01', '2026-02-28'),
        );
    }

    public function testOnlyTheDatesOfTheRangeCount(): void
    {
        $route = $this->route();
        $this->stop($route, '2026-01-31', 'P-A', StopType::Pickup, StopStatus::Completed);
        $this->stop($route, '2026-02-01', 'P-B', StopType::Pickup, StopStatus::Pending);
        $this->stop($route, '2026-02-28', 'P-C', StopType::Pickup, StopStatus::Completed);
        $this->stop($route, '2026-03-01', 'P-D', StopType::Pickup, StopStatus::Completed);

        self::assertEquals(
            new TripCountDto(trips: 2, completed: 1, cancelled: 0, pending: 1, citizens: 2),
            $this->trips('2026-02-01', '2026-02-28'),
        );
    }

    public function testRangeWithNoTripsHasZeroCounts(): void
    {
        self::assertEquals(new TripCountDto(0, 0, 0, 0, 0), $this->trips('2026-02-01', '2026-02-28'));
    }

    private function trips(string $firstDate, string $lastDate): TripCountDto
    {
        $this->entityManager->flush();
        $stopRows = $this->entityManager->getRepository(StopRow::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);

        return $stopRows->countTrips(DateRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate)));
    }

    private function route(): Route
    {
        $subAgreement = new SubAgreement()->setReference('R');
        $this->entityManager->persist($subAgreement);
        $route = new Route('R-01', $subAgreement);
        $this->entityManager->persist($route);

        return $route;
    }

    private function stop(Route $route, string $date, string $person, StopType $type, StopStatus $status): void
    {
        $serviceDate = new \DateTimeImmutable($date);
        $row = new StopRowDto(
            rowNumber: ++$this->rowKey,
            rowKey: 'S-'.$this->rowKey,
            route: $route->getSourceKey(),
            personId: $person,
            serviceDate: $serviceDate,
            run: 1,
            stopType: $type,
            stopStatus: $status,
            passengersAfter: null,
            nextDistanceKm: null,
            nextDurationMin: null,
            plannedTime: $serviceDate->setTime(7, 0),
            actualTime: null,
            plannedSolo: null,
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

    private function importBase(): void
    {
        $scenario = new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml');
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);
        new ExtractWriter()->write($path, $provider->default(), $scenario->rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $importer->import($path, ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 31), $provider->default(), null, 'base.xlsx');
        unlink($path);
    }
}

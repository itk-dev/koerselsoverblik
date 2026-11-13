<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\Route;
use App\Entity\RouteRun;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Import\RouteRunDto;
use App\Report\DateRangeDto;
use App\Report\SoloCountDto;
use App\Repository\RouteRunRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the real solo runs of a date range: the runs with exactly one citizen in the car at a
 * time.
 */
final class RouteRunSoloCountTest extends KernelTestCase
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
     * The expected values are in the "expected" section of the scenario: 2 solo runs of 4 runs.
     */
    public function testBaseScenario(): void
    {
        $this->importBase();

        self::assertEquals(new SoloCountDto(runs: 4, soloRuns: 2), $this->solo('2026-01-01', '2026-01-31'));
    }

    /**
     * A cancelled run, a run with no passenger count and a run with no citizen in the car are not
     * counted. A run with a peak of 0 is neither solo nor shared.
     */
    public function testOnlyRunsWithACitizenThatAreNotCancelledCount(): void
    {
        $route = $this->route();
        $this->routeRun($route, '2026-02-02', 1, peak: 1);
        $this->routeRun($route, '2026-02-02', 2, peak: 3);
        $this->routeRun($route, '2026-02-02', 3, peak: null);
        $this->routeRun($route, '2026-02-02', 4, peak: null, cancelled: true);
        $this->routeRun($route, '2026-02-02', 5, peak: 0);

        self::assertEquals(new SoloCountDto(runs: 2, soloRuns: 1), $this->solo('2026-02-01', '2026-02-28'));
    }

    public function testOnlyTheDatesOfTheRangeCount(): void
    {
        $route = $this->route();
        $this->routeRun($route, '2026-01-31', 1, peak: 1);
        $this->routeRun($route, '2026-02-01', 1, peak: 1);
        $this->routeRun($route, '2026-02-28', 1, peak: 2);
        $this->routeRun($route, '2026-03-01', 1, peak: 1);

        self::assertEquals(new SoloCountDto(runs: 2, soloRuns: 1), $this->solo('2026-02-01', '2026-02-28'));
    }

    /**
     * The runs of an ignored sub-agreement do not count.
     */
    public function testIgnoredSubAgreementDoesNotCount(): void
    {
        $route = $this->route();
        $ignored = $this->route('I', ignored: true);
        $this->routeRun($route, '2026-02-02', 1, peak: 2);
        $this->routeRun($ignored, '2026-02-02', 1, peak: 1);
        $this->routeRun($ignored, '2026-02-02', 2, peak: 3);

        self::assertEquals(new SoloCountDto(runs: 1, soloRuns: 0), $this->solo('2026-02-01', '2026-02-28'));
    }

    public function testRangeWithNoRunsHasZeroCounts(): void
    {
        self::assertEquals(new SoloCountDto(0, 0), $this->solo('2026-02-01', '2026-02-28'));
    }

    /**
     * With a supplier, only the routes of the sub-agreements of that supplier count. An ignored
     * sub-agreement of the supplier does not count.
     */
    public function testOnlyTheRoutesOfTheSupplierCount(): void
    {
        $supplier = new Supplier()->setName('Vognmand A');
        $this->entityManager->persist($supplier);
        $this->routeRun($this->route('A', supplier: $supplier), '2026-02-02', 1, peak: 1);
        $this->routeRun($this->route('AI', ignored: true, supplier: $supplier), '2026-02-02', 1, peak: 1);
        $this->routeRun($this->route(), '2026-02-02', 1, peak: 2);
        $this->entityManager->flush();

        self::assertEquals(new SoloCountDto(runs: 1, soloRuns: 1), $this->solo('2026-02-01', '2026-02-28', $supplier->getId()));
        self::assertEquals(new SoloCountDto(runs: 2, soloRuns: 1), $this->solo('2026-02-01', '2026-02-28'));
    }

    private function solo(string $firstDate, string $lastDate, ?int $supplier = null): SoloCountDto
    {
        $this->entityManager->flush();
        $routeRuns = $this->entityManager->getRepository(RouteRun::class);
        self::assertInstanceOf(RouteRunRepository::class, $routeRuns);

        return $routeRuns->countSoloRuns(DateRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate)), $supplier);
    }

    private function route(string $reference = 'R', bool $ignored = false, ?Supplier $supplier = null): Route
    {
        $subAgreement = new SubAgreement()->setReference($reference)->setIgnored($ignored)->setSupplier($supplier);
        $this->entityManager->persist($subAgreement);
        $route = new Route($reference.'-01', $subAgreement);
        $this->entityManager->persist($route);

        return $route;
    }

    private function routeRun(Route $route, string $date, int $run, ?int $peak, bool $cancelled = false): void
    {
        $this->entityManager->persist(new RouteRun($route, new \DateTimeImmutable($date), $run, new RouteRunDto(null, null, null, null, null, null, $cancelled, $peak)));
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

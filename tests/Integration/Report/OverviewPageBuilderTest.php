<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\RouteRun;
use App\Entity\StopRow;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Report\OverviewPageBuilder;
use App\Report\PeriodFilter;
use App\Report\PeriodRangeFactory;
use App\Report\PeriodSelectionDto;
use App\Report\SoloCountDto;
use App\Report\TripCountDto;
use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the overview has the trips and the citizens of the twelve months up to the month.
 */
final class OverviewPageBuilderTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    public function testPageHasTwelveMonths(): void
    {
        $this->importBase();

        $page = $this->builder()->build(self::select('2026-02-10', ''));

        self::assertCount(12, $page->months);
        self::assertCount(12, $page->counts);
        self::assertSame('2025-03', $page->months[0]->query());
        self::assertEquals(new TripCountDto(7, 5, 1, 1, 6), $page->counts[10]);
        self::assertEquals(new TripCountDto(0, 0, 0, 0, 0), $page->current());
        // The base scenario is on 5 January, the comparison month.
        self::assertEquals(new TripCountDto(7, 5, 1, 1, 6), $page->comparison);
        self::assertCount(12, $page->solos);
        self::assertEquals(new SoloCountDto(4, 2), $page->solos[10]);
        self::assertEquals(new SoloCountDto(0, 0), $page->currentSolo());
        self::assertEquals(new SoloCountDto(4, 2), $page->comparisonSolo);
    }

    /**
     * The data starts in January 2026, so December 2025 is no comparison.
     */
    public function testComparisonBeforeTheDataHasNoFigures(): void
    {
        $this->importBase();

        $page = $this->builder()->build(self::select('2026-01-10', '2026-01-01'));

        self::assertEquals(new TripCountDto(7, 5, 1, 1, 6), $page->current());
        self::assertNull($page->comparison);
        self::assertNull($page->comparisonSolo);
    }

    private static function select(string $date, string $dataStart): PeriodSelectionDto
    {
        $today = new \DateTimeImmutable('2026-11-09');

        return new PeriodRangeFactory($dataStart)->create(PeriodFilter::fromQuery(null, $date, null, null, $today), $today);
    }

    private function builder(): OverviewPageBuilder
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $stopRows = $entityManager->getRepository(StopRow::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);
        $routeRuns = $entityManager->getRepository(RouteRun::class);
        self::assertInstanceOf(RouteRunRepository::class, $routeRuns);

        return new OverviewPageBuilder($stopRows, $routeRuns);
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

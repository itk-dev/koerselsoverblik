<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\Route;
use App\Entity\RouteRun;
use App\Entity\StopRow;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Report\PeriodFilter;
use App\Report\PeriodRangeFactory;
use App\Report\PeriodSelectionDto;
use App\Report\PunctualityCalculator;
use App\Report\PunctualityCountDto;
use App\Report\PunctualityPageBuilder;
use App\Repository\RouteRepository;
use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the page has the figures of the twelve months up to the month.
 */
final class PunctualityPageBuilderTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    public function testPageHasTwelveMonths(): void
    {
        $this->importBase();

        $page = $this->builder()->build(self::select('month', '2026-02-10'));

        self::assertCount(12, $page->months);
        self::assertCount(12, $page->reports);
        self::assertSame('2025-03', $page->months[0]->query());
        self::assertEquals(new PunctualityCountDto(5, 6), $page->reports[10]->total->dropOffPerCitizen);
        // The base scenario is on 5 January, the comparison month.
        self::assertEquals(new PunctualityCountDto(5, 6), $page->comparison?->total->dropOffPerCitizen);
        self::assertEquals(new PunctualityCountDto(0, 0), $page->current()->total->dropOffPerCitizen);
        self::assertSame(90, $page->target);
    }

    /**
     * Week 2 of 2026 (5 to 11 January) has the base scenario. The week before it has no data.
     */
    public function testWeekHasItsOwnFigures(): void
    {
        $this->importBase();

        $page = $this->builder()->build(self::select('week', '2026-01-07'));

        self::assertEquals(new PunctualityCountDto(5, 6), $page->current()->total->dropOffPerCitizen);
        self::assertEquals(new PunctualityCountDto(0, 0), $page->comparison?->total->dropOffPerCitizen);
        self::assertSame('2026-01', $page->months[11]->query());
    }

    private static function select(string $type, string $date): PeriodSelectionDto
    {
        $today = new \DateTimeImmutable('2026-11-09');

        return new PeriodRangeFactory()->create(PeriodFilter::fromQuery($type, $date, null, null, $today), $today);
    }

    private function builder(): PunctualityPageBuilder
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $stopRows = $entityManager->getRepository(StopRow::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);
        $routeRuns = $entityManager->getRepository(RouteRun::class);
        self::assertInstanceOf(RouteRunRepository::class, $routeRuns);
        $routes = $entityManager->getRepository(Route::class);
        self::assertInstanceOf(RouteRepository::class, $routes);

        // The container removes a private service that no other service uses yet.
        return new PunctualityPageBuilder(new PunctualityCalculator($stopRows, $routeRuns, $routes, 5), 90);
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

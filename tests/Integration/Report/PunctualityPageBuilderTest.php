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
use App\Report\MonthDto;
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
        $month = MonthDto::fromQuery('2026-02', new \DateTimeImmutable('2026-11-09'));

        $page = $this->builder()->build($month);

        self::assertSame('2026-02', $page->month()->query());
        self::assertCount(12, $page->months);
        self::assertSame('2025-03', $page->months[0]->query());
        // The base scenario is on 5 January, the month before the month of the page.
        self::assertEquals(new PunctualityCountDto(5, 6), $page->previous()?->total->dropOffPerCitizen);
        self::assertEquals(new PunctualityCountDto(0, 0), $page->current()->total->dropOffPerCitizen);
        self::assertSame(90, $page->target);
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

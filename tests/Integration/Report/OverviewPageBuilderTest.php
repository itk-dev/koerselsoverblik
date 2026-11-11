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
use App\Report\MonthDto;
use App\Report\OverviewPageBuilder;
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
        $month = MonthDto::fromQuery('2026-02', new \DateTimeImmutable('2026-11-09'));

        $page = $this->builder()->build($month);

        self::assertSame('2026-02', $page->month()->query());
        self::assertCount(12, $page->months);
        self::assertCount(12, $page->counts);
        self::assertSame('2025-03', $page->months[0]->query());
        // The base scenario is on 5 January, the month before the month of the page.
        self::assertEquals(new TripCountDto(7, 5, 1, 1, 6), $page->counts[10]);
        self::assertEquals(new TripCountDto(0, 0, 0, 0, 0), $page->current());
        self::assertCount(12, $page->solos);
        self::assertEquals(new SoloCountDto(4, 2), $page->solos[10]);
        self::assertEquals(new SoloCountDto(0, 0), $page->currentSolo());
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

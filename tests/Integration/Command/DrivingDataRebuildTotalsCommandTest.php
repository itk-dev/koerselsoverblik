<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\Route;
use App\Entity\RouteDayTotals;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Report\DayTotalsDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Checks that the command builds the totals of each route and day of all dates again, for
 * example after a change of the tolerance.
 */
final class DrivingDataRebuildTotalsCommandTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    /**
     * A row with another tolerance, and a row of a date with no stop rows, are replaced. The base
     * scenario has three routes on one date.
     */
    public function testAllDatesAreBuiltAgain(): void
    {
        $this->importBase();
        $entityManager = $this->entityManager();
        $route = $entityManager->getRepository(Route::class)->findOneBy([]);
        self::assertInstanceOf(Route::class, $route);
        $entityManager->createQuery('UPDATE '.RouteDayTotals::class.' totals SET totals.toleranceMinutes = 10')->execute();
        $entityManager->persist(new RouteDayTotals($route, new \DateTimeImmutable('2025-06-01'), 10, DayTotalsDto::empty()));
        $entityManager->flush();

        $tester = $this->execute();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Built 3 totals of a route and a day.', $tester->getDisplay());
        $entityManager->clear();
        $rows = $entityManager->getRepository(RouteDayTotals::class)->findAll();
        self::assertSame([5, 5, 5], array_map(static fn (RouteDayTotals $row): int => $row->getToleranceMinutes(), $rows));
    }

    public function testNoStopRowsGiveNoTotals(): void
    {
        $tester = $this->execute();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Built 0 totals of a route and a day.', $tester->getDisplay());
    }

    private function execute(): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:driving-data:rebuild-totals'));
        $tester->execute([]);

        return $tester;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
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

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\LocationDayTotals;
use App\Entity\MonthCitizens;
use App\Entity\Route;
use App\Entity\RouteDayTotals;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Report\DayTotalsDto;
use App\Report\PunctualityCountDto;
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
     * scenario has three routes on one date, and measured stops at two locations.
     */
    public function testAllDatesAreBuiltAgain(): void
    {
        $this->importBase();
        $entityManager = $this->entityManager();
        $route = $entityManager->getRepository(Route::class)->findOneBy([]);
        self::assertInstanceOf(Route::class, $route);
        $entityManager->createQuery('UPDATE '.RouteDayTotals::class.' totals SET totals.toleranceMinutes = 10')->execute();
        $entityManager->createQuery('UPDATE '.LocationDayTotals::class.' totals SET totals.toleranceMinutes = 10')->execute();
        $entityManager->persist(new RouteDayTotals($route, new \DateTimeImmutable('2025-06-01'), 10, DayTotalsDto::empty()));
        $entityManager->persist(new LocationDayTotals($route, new \DateTimeImmutable('2025-06-01'), 'Skole 1', 10, new PunctualityCountDto(1, 1)));
        $entityManager->flush();

        $tester = $this->execute();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Built 3 totals of a route and a day, 2 totals of a location and a day, and the citizens of each month.', self::display($tester));
        $entityManager->clear();
        $rows = $entityManager->getRepository(RouteDayTotals::class)->findAll();
        self::assertSame([5, 5, 5], array_map(static fn (RouteDayTotals $row): int => $row->getToleranceMinutes(), $rows));
        $locations = $entityManager->getRepository(LocationDayTotals::class)->findAll();
        self::assertSame([5, 5], array_map(static fn (LocationDayTotals $row): int => $row->getToleranceMinutes(), $locations));
        self::assertSame(1, $entityManager->getRepository(MonthCitizens::class)->count());
    }

    /**
     * The command builds the dates in steps of APP_DRIVING_DATA_MAX_DAYS (7 in the tests). The
     * two dates of the scenario are 15 days apart, so the second date is in the third step.
     */
    public function testDatesInMoreStepsAreBuilt(): void
    {
        $this->import('two_weeks.yaml', '2026-01-05', '2026-01-20');
        $entityManager = $this->entityManager();
        $entityManager->createQuery('DELETE FROM '.RouteDayTotals::class.' totals')->execute();

        $tester = $this->execute();

        self::assertStringContainsString('Built 2 totals of a route and a day, 0 totals of a location and a day, and the citizens of each month.', self::display($tester));
        $entityManager->clear();
        $rows = $entityManager->getRepository(RouteDayTotals::class)->findBy([], ['serviceDate' => 'ASC']);
        self::assertSame(['2026-01-05', '2026-01-20'], array_map(static fn (RouteDayTotals $row): string => $row->getServiceDate()->format('Y-m-d'), $rows));
        self::assertSame([1, 1], array_map(static fn (RouteDayTotals $row): int => $row->getTotals()->trips, $rows));
    }

    /**
     * With no stop rows, the command deletes the old citizens of each month.
     */
    public function testNoStopRowsGiveNoTotals(): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist(new MonthCitizens(new \DateTimeImmutable('2025-06-01'), null, 3));
        $entityManager->flush();

        $tester = $this->execute();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Built 0 totals of a route and a day, 0 totals of a location and a day', self::display($tester));
        self::assertSame(0, $entityManager->getRepository(MonthCitizens::class)->count());
    }

    private function execute(): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:driving-data:rebuild-totals'));
        $tester->execute([]);

        return $tester;
    }

    /**
     * Gives the output on one line, because the success box breaks a long message into lines.
     */
    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function importBase(): void
    {
        $this->import('base.yaml', '2026-01-05', '2026-01-05');
    }

    private function import(string $file, string $first, string $last): void
    {
        $scenario = new ScenarioLoader()->load(self::SCENARIOS.'/'.$file);
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);
        new ExtractWriter()->write($path, $provider->default(), $scenario->rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $importer->import($path, ImportRangeDto::create(new \DateTimeImmutable($first), new \DateTimeImmutable($last), 31), $provider->default(), null, $file);
        unlink($path);
    }
}

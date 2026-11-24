<?php

declare(strict_types=1);

namespace App\Tests\Integration\Report;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Report\ActivityPageBuilder;
use App\Report\DayTotalsDto;
use App\Report\PeriodFilter;
use App\Report\PeriodRangeFactory;
use App\Report\PeriodSelectionDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the activity page has the totals of the selected period, the comparison period and
 * the twelve months up to the period. The base scenario has 7 trips on 5 January 2026: 5
 * completed, 1 cancelled and 1 with no registration, and 4 runs that are not cancelled.
 */
final class ActivityPageBuilderTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    public function testPageHasTwelveMonths(): void
    {
        $this->importBase();

        $page = $this->builder()->build(self::select('2026-02-10', ''));

        self::assertCount(12, $page->months);
        self::assertCount(12, $page->monthTotals);
        self::assertSame('2025-03', $page->months[0]->query());
        self::assertEquals(DayTotalsDto::empty(), $page->monthTotals[0]);
        self::assertSame([7, 5, 1, 1, 4], self::counts($page->monthTotals[10]));
        self::assertEquals(DayTotalsDto::empty(), $page->current());
        // The base scenario is on 5 January, the comparison month.
        self::assertNotNull($page->comparison);
        self::assertSame([7, 5, 1, 1, 4], self::counts($page->comparison));
        self::assertFalse($page->comparisonIsPartial);
    }

    /**
     * The data starts in January 2026, so December 2025 is no comparison.
     */
    public function testComparisonBeforeTheDataHasNoTotals(): void
    {
        $this->importBase();

        $page = $this->builder()->build(self::select('2026-01-10', '2026-01-01'));

        self::assertSame([7, 5, 1, 1, 4], self::counts($page->current()));
        self::assertNull($page->comparison);
    }

    /**
     * The data starts on 2 January, so January has fewer days than February. The page gets the
     * totals of January, but it does not compare the counts.
     */
    public function testComparisonThatStartsBeforeTheDataIsPartial(): void
    {
        $this->importBase();

        $page = $this->builder()->build(self::select('2026-02-10', '2026-01-02'));

        self::assertNotNull($page->comparison);
        self::assertTrue($page->comparisonIsPartial);
        self::assertNull($page->trips()->change);
    }

    /**
     * Week 2 of 2026 (5 to 11 January) has the base scenario. A week is not a month of the
     * history, so it has its own totals. The week before it has no data.
     */
    public function testWeekHasItsOwnTotals(): void
    {
        $this->importBase();

        $page = $this->builder()->build(self::select('2026-01-07', '', 'week'));

        self::assertSame([7, 5, 1, 1, 4], self::counts($page->current()));
        self::assertEquals(DayTotalsDto::empty(), $page->comparison);
        self::assertSame([7, 5, 1, 1, 4], self::counts($page->monthTotals[11]));
    }

    /**
     * With a supplier, the totals only have the routes of its sub-agreements. BOOKED has 1
     * completed trip and 1 run.
     */
    public function testSupplierFilterLimitsTheTotals(): void
    {
        $this->importBase();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $supplier = new Supplier()->setName('Vognmand A');
        $entityManager->persist($supplier);
        $subAgreement = $entityManager->getRepository(SubAgreement::class)->findOneBy(['reference' => 'BOOKED']);
        self::assertInstanceOf(SubAgreement::class, $subAgreement);
        $subAgreement->setSupplier($supplier);
        $entityManager->flush();

        $page = $this->builder()->build(self::select('2026-01-10', '', supplier: (string) $supplier->getId()));

        self::assertSame([1, 1, 0, 0, 1], self::counts($page->current()));
        self::assertSame([1, 1, 0, 0, 1], self::counts($page->monthTotals[11]));
    }

    /**
     * @return list<int> the trips, the completed, cancelled and pending trips, and the runs that are not cancelled
     */
    private static function counts(DayTotalsDto $totals): array
    {
        return [$totals->trips, $totals->completedTrips, $totals->cancelledTrips, $totals->pendingTrips, $totals->notCancelledRuns];
    }

    private static function select(string $date, string $dataStart, ?string $type = null, ?string $supplier = null): PeriodSelectionDto
    {
        return new PeriodRangeFactory($dataStart)->create(PeriodFilter::fromQuery($type, $date, null, $supplier, new \DateTimeImmutable('2026-11-09')));
    }

    private function builder(): ActivityPageBuilder
    {
        $builder = self::getContainer()->get(ActivityPageBuilder::class);
        self::assertInstanceOf(ActivityPageBuilder::class, $builder);

        return $builder;
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

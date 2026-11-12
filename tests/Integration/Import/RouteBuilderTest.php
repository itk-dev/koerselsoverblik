<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\ImportBatch;
use App\Entity\RejectedStopRow;
use App\Entity\RouteRun;
use App\Entity\SourceRow;
use App\Entity\StopRow;
use App\Import\Extract\StopRowParser;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingProvider;
use App\Import\RouteBuilder;
use App\Import\RouteRunCalculator;
use App\Import\StopRowRangeDeleter;
use App\Import\StopRowReplacer;
use App\Repository\RejectedStopRowRepository;
use App\Repository\RouteRunRepository;
use App\Repository\SourceRowRepository;
use App\Repository\StopRowRepository;
use App\Repository\SubAgreementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the route runs of a range are built again from the stored stop rows, and that the
 * runs outside the range stay.
 */
final class RouteBuilderTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    private string $path = '';

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    /**
     * The expected values come from the base scenario.
     */
    public function testBaseScenarioGivesFourRouteRuns(): void
    {
        $this->importRows($this->scenarioRows(), self::range('2026-01-05'));

        $built = $this->builder()->rebuild(self::range('2026-01-05'));

        self::assertSame(4, $built);
        self::assertSame([
            'BOOKED/1' => ['2026-01-05 13:00', '2026-01-05 13:03', 180, '2026-01-05 13:30', '2026-01-05 13:30', 0, false],
            'R-01/1' => ['2026-01-05 07:00', '2026-01-05 07:02', 120, '2026-01-05 07:35', '2026-01-05 07:39', 240, false],
            'R-01/2' => ['2026-01-05 09:00', '2026-01-05 09:05', 300, '2026-01-05 09:20', '2026-01-05 09:31', 660, false],
            // The run ends after midnight, on the next day.
            'R-02/1' => ['2026-01-05 23:40', '2026-01-05 23:41', 60, '2026-01-06 00:30', '2026-01-06 00:33', 180, false],
        ], $this->storedRuns());
    }

    public function testSecondRebuildGivesTheSameRuns(): void
    {
        $this->importRows($this->scenarioRows(), self::range('2026-01-05'));
        $this->builder()->rebuild(self::range('2026-01-05'));
        $first = $this->storedRuns();

        $built = $this->builder()->rebuild(self::range('2026-01-05'));

        self::assertSame(4, $built);
        self::assertSame($first, $this->storedRuns());
    }

    /**
     * The builder deletes only the runs in the range. A date with no stop rows then has no runs.
     */
    public function testRebuildChangesOnlyTheRunsInTheRange(): void
    {
        $nextDay = array_map(
            static fn (ExtractRow $row, int $index): ExtractRow => new ExtractRow(['service_date' => new \DateTimeImmutable('2026-01-06')] + $row->values, 'next-'.$index, $row->raw, $row->kept),
            $this->scenarioRows(),
            array_keys($this->scenarioRows()),
        );
        $this->importRows([...$this->scenarioRows(), ...$nextDay], self::range('2026-01-05', '2026-01-06'));
        self::assertSame(8, $this->builder()->rebuild(self::range('2026-01-05', '2026-01-06')));

        $this->entityManager->createQuery('DELETE FROM '.StopRow::class.' row WHERE row.serviceDate = :date')
            ->setParameter('date', new \DateTimeImmutable('2026-01-06'), Types::DATE_IMMUTABLE)
            ->execute();
        $built = $this->builder()->rebuild(self::range('2026-01-06'));

        self::assertSame(0, $built);
        self::assertSame(['2026-01-05'], array_values(array_unique(array_map(
            static fn (RouteRun $run): string => $run->getServiceDate()->format('Y-m-d'),
            $this->routeRuns(),
        ))));
        self::assertCount(4, $this->routeRuns());
    }

    /**
     * The builder writes the runs in groups, so the memory use does not grow with the range.
     */
    public function testMoreRunsThanOneGroupAreAllStored(): void
    {
        $values = $this->scenarioRows()[0]->values;
        $rows = array_map(static fn (int $run): ExtractRow => new ExtractRow(['run' => $run] + $values, 'S-'.$run), range(1, 1000));
        $this->importRows($rows, self::range('2026-01-05'));
        $flushes = new FlushCounter();
        $this->entityManager->getEventManager()->addEventListener(Events::postFlush, $flushes);

        $built = $this->builder()->rebuild(self::range('2026-01-05'));

        // One flush for each full group, and one for the runs after the last full group.
        self::assertSame(3, $flushes->count);
        self::assertSame(1000, $built);
        self::assertCount(1000, $this->routeRuns());
    }

    /**
     * Fewer runs than one group are written in one flush. Then the entity manager forgets them.
     */
    public function testRunsAreWrittenInOneFlushAndForgotten(): void
    {
        $this->importRows($this->scenarioRows(), self::range('2026-01-05'));
        $flushes = new FlushCounter();
        $this->entityManager->getEventManager()->addEventListener(Events::postFlush, $flushes);

        $this->builder()->rebuild(self::range('2026-01-05'));

        self::assertSame(1, $flushes->count);
        self::assertSame([], $this->entityManager->getUnitOfWork()->getIdentityMap()[RouteRun::class] ?? []);
    }

    private function builder(): RouteBuilder
    {
        $routeRuns = $this->entityManager->getRepository(RouteRun::class);
        self::assertInstanceOf(RouteRunRepository::class, $routeRuns);
        $stopRows = $this->entityManager->getRepository(StopRow::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);

        // The container removes a private service that no other service uses yet.
        return new RouteBuilder($routeRuns, $stopRows, $this->entityManager, new RouteRunCalculator());
    }

    /**
     * @return list<ExtractRow>
     */
    private function scenarioRows(): array
    {
        return new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml')->rows;
    }

    /**
     * @param list<ExtractRow> $rows
     */
    private function importRows(array $rows, ImportRangeDto $range): void
    {
        $mapping = $this->mapping();
        new ExtractWriter()->write($this->path, $mapping, $rows);
        $batch = new ImportBatch($mapping->name, 'extract.xlsx', null, new \DateTimeImmutable(), $range);
        $this->entityManager->persist($batch);
        $this->entityManager->flush();

        $parser = self::getContainer()->get(StopRowParser::class);
        self::assertInstanceOf(StopRowParser::class, $parser);
        $subAgreements = self::getContainer()->get(SubAgreementRepository::class);
        self::assertInstanceOf(SubAgreementRepository::class, $subAgreements);
        $stopRows = $this->entityManager->getRepository(StopRow::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);
        $rejectedRows = $this->entityManager->getRepository(RejectedStopRow::class);
        self::assertInstanceOf(RejectedStopRowRepository::class, $rejectedRows);
        $sourceRows = $this->entityManager->getRepository(SourceRow::class);
        self::assertInstanceOf(SourceRowRepository::class, $sourceRows);
        new StopRowReplacer($this->entityManager, $subAgreements, new StopRowRangeDeleter($stopRows, $rejectedRows, $sourceRows))->replace($range, $mapping, $parser->parse($this->path, $mapping, $range), $batch);
    }

    private function mapping(): MappingDto
    {
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);

        return $provider->default();
    }

    /**
     * @return list<RouteRun>
     */
    private function routeRuns(): array
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(RouteRun::class)->findAll();
    }

    /**
     * @return array<string, array{?string, ?string, ?int, ?string, ?string, ?int, bool}> the values of each run, keyed by the route name and the run
     */
    private function storedRuns(): array
    {
        $runs = [];
        foreach ($this->routeRuns() as $run) {
            $runs[$run->getRoute()->getSourceKey().'/'.$run->getRun()] = [
                $run->getFirstPickupPlanned()?->format('Y-m-d H:i'),
                $run->getFirstPickupActual()?->format('Y-m-d H:i'),
                $run->getFirstPickupDelaySeconds(),
                $run->getLastDropPlanned()?->format('Y-m-d H:i'),
                $run->getLastDropActual()?->format('Y-m-d H:i'),
                $run->getLastDropDelaySeconds(),
                $run->isCancelled(),
            ];
        }
        ksort($runs);

        return $runs;
    }

    private static function range(string $firstDate, ?string $lastDate = null): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate ?? $firstDate), 31);
    }
}

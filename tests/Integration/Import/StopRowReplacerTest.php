<?php

namespace App\Tests\Integration\Import;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\ImportBatch;
use App\Entity\RejectedStopRow;
use App\Entity\Route;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Import\Extract\RejectionCountDto;
use App\Import\Extract\RejectionReason;
use App\Import\Extract\SourceCells;
use App\Import\Extract\StopRowParser;
use App\Import\ImportRangeDto;
use App\Import\ImportResultDto;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingProvider;
use App\Import\StopRowReplacer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that each date in the range of an import holds exactly the rows of the file, with their
 * copies, and that the rejected rows in the range are stored with their copies.
 */
final class StopRowReplacerTest extends KernelTestCase
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

    public function testBaseScenarioIsStoredWithTheCopies(): void
    {
        $this->writeScenario('base');

        $result = $this->import(self::range('2026-01-05'));

        self::assertSame(0, $result->deletedRows);
        self::assertSame(14, $result->insertedRows);
        self::assertSame(0, $result->rejectedRows);
        self::assertSame(0, $result->warningCount);
        self::assertSame([], $result->emptyDates);
        self::assertSame(14, $this->stored(StopRow::class));
        self::assertSame(3, $this->stored(Route::class));

        $rows = $this->stopRows();
        self::assertSame('default', $rows[0]->getMappingName());
        self::assertSame('2026-01-05', $rows[0]->getServiceDate()->format('Y-m-d'));
        // The copy gives the cells of the row again.
        $cells = SourceCells::cells($rows[0]->getSourceValues());
        self::assertSame($rows[0]->getRoute()->getSourceKey(), $cells['RouteName']->getValue());
        self::assertSame($rows[0]->getRowKey(), $cells['RouteStopID']->getValue());
    }

    /**
     * A new route gets its sub-agreement from the route name. A new sub-agreement has no
     * assignment, so its routes count as "Unknown".
     */
    public function testNewRoutesGetSubAgreementsWithNoAssignment(): void
    {
        $this->writeScenario('base');

        $this->import(self::range('2026-01-05'));

        $references = array_map(
            static fn (SubAgreement $subAgreement): string => $subAgreement->getReference(),
            $this->entityManager->getRepository(SubAgreement::class)->findBy([], ['reference' => 'ASC']),
        );
        self::assertSame(['BOOKED', 'R'], $references);
        foreach ($this->entityManager->getRepository(SubAgreement::class)->findAll() as $subAgreement) {
            self::assertCount(0, $subAgreement->getAssignments());
        }
    }

    public function testExistingRouteAndSubAgreementAreUsedAgain(): void
    {
        $subAgreement = (new SubAgreement())->setReference('R');
        $route = new Route('R-01', $subAgreement);
        $this->entityManager->persist($subAgreement);
        $this->entityManager->persist($route);
        $this->entityManager->flush();
        $this->writeScenario('base');

        $this->import(self::range('2026-01-05'));

        self::assertSame(3, $this->stored(Route::class));
        self::assertSame(2, $this->stored(SubAgreement::class));
        $routeIds = array_map(static fn (StopRow $row): ?int => $row->getRoute()->getId(), $this->stopRows());
        self::assertContains($route->getId(), $routeIds);
    }

    public function testSecondImportOfTheSameFileGivesTheSameRows(): void
    {
        $this->writeScenario('base');
        $this->import(self::range('2026-01-05'));

        $result = $this->import(self::range('2026-01-05'));

        self::assertSame(14, $result->deletedRows);
        self::assertSame(14, $result->insertedRows);
        self::assertSame(14, $this->stored(StopRow::class));
        self::assertSame(3, $this->stored(Route::class));
    }

    public function testCorrectedFileRemovesTheRowThatIsNotInIt(): void
    {
        $rows = $this->writeScenario('base');
        $this->import(self::range('2026-01-05'));
        (new ExtractWriter())->write($this->path, $this->mapping(), \array_slice($rows, 0, 13));

        $result = $this->import(self::range('2026-01-05'));

        self::assertSame(14, $result->deletedRows);
        self::assertSame(13, $result->insertedRows);
        self::assertSame(13, $this->stored(StopRow::class));
    }

    /**
     * A date of the range with no rows in the file becomes empty. A row after the range is
     * rejected, but not stored, because the upload for its date imports it.
     */
    public function testRangeEdges(): void
    {
        $this->writeScenario('range_edges');

        $result = $this->import(self::range('2026-01-05', '2026-01-07'));

        self::assertSame(4, $result->insertedRows);
        self::assertSame(1, $result->rejectedRows);
        self::assertEquals([new RejectionCountDto(FieldName::ServiceDate, RejectionReason::OutsideRange, 1, [8])], $result->rejections);
        self::assertSame(['2026-01-06'], $result->emptyDates);
        self::assertSame(0, $this->stored(RejectedStopRow::class));
    }

    public function testRowsOutsideTheRangeDoNotChange(): void
    {
        $this->writeScenario('base');
        $this->import(self::range('2026-01-05'));

        $result = $this->import(self::range('2026-01-06'));

        self::assertSame(0, $result->deletedRows);
        self::assertSame(0, $result->insertedRows);
        self::assertSame(['2026-01-06'], $result->emptyDates);
        self::assertSame(14, $this->stored(StopRow::class));
    }

    /**
     * Each extract holds all routes of a day, so an import replaces the rows of all mappings.
     */
    public function testRowsOfAnotherMappingInTheRangeAreDeleted(): void
    {
        $this->writeScenario('base');
        $this->import(self::range('2026-01-05'));
        $this->entityManager->createQuery('UPDATE '.StopRow::class." row SET row.mappingName = 'other'")->execute();
        $this->writeScenario('range_edges');

        $result = $this->import(self::range('2026-01-05', '2026-01-07'));

        self::assertSame(14, $result->deletedRows);
        self::assertSame(4, $this->stored(StopRow::class));
    }

    /**
     * The first row with a row id is kept. A later row with the same row id is rejected, and
     * stored with its copy.
     */
    public function testDuplicateRowIdIsRejectedAndStored(): void
    {
        $this->writeScenario('duplicate_row_ids');

        $result = $this->import(self::range('2026-01-05'));

        self::assertSame(2, $result->insertedRows);
        self::assertSame(1, $result->rejectedRows);
        self::assertEquals([new RejectionCountDto(null, RejectionReason::DuplicateRowId, 1, [6])], $result->rejections);
        [$rejected] = $this->entityManager->getRepository(RejectedStopRow::class)->findAll();
        self::assertSame(6, $rejected->getRowNumber());
        self::assertSame(RejectionReason::DuplicateRowId, $rejected->getReason());
        self::assertNull($rejected->getField());
        self::assertSame('2026-01-05', $rejected->getServiceDate()?->format('Y-m-d'));
        self::assertSame('D-1', $rejected->getSourceValues()['RouteStopID']);
    }

    /**
     * A rejected row in the range is stored with its copy, also a row with no valid date. A bad
     * value in an optional field gives a warning, and the row is imported.
     */
    public function testRejectedRowsAreStoredWithTheirCopies(): void
    {
        $this->writeScenario('bad_values');

        $result = $this->import(self::range('2026-01-05'));

        self::assertSame(4, $result->insertedRows);
        self::assertSame(5, $result->rejectedRows);
        self::assertSame(3, $result->warningCount);
        self::assertCount(3, $result->warnings);
        $rejected = $this->entityManager->getRepository(RejectedStopRow::class)->findBy([], ['rowNumber' => 'ASC']);
        self::assertCount(5, $rejected);
        $run = array_values(array_filter($rejected, static fn (RejectedStopRow $row): bool => FieldName::Run === $row->getField()));
        self::assertCount(1, $run);
        self::assertSame(RejectionReason::NotWholeNumber, $run[0]->getReason());
        self::assertSame('abc', $run[0]->getSourceValues()['blocknumber']);
        $withoutDate = array_filter($rejected, static fn (RejectedStopRow $row): bool => null === $row->getServiceDate());
        self::assertCount(1, $withoutDate);
    }

    /**
     * The result has the counts and the reasons, never a value of the extract.
     */
    public function testResultHasNoValues(): void
    {
        $this->writeScenario('bad_values');

        $result = $this->import(self::range('2026-01-05'));

        $json = json_encode($result, \JSON_THROW_ON_ERROR);
        foreach (['abc', 'far', 'maybe', 'not-a-date', 'late', 'soon', 'UNKNOWN'] as $value) {
            self::assertStringNotContainsString($value, $json);
        }
    }

    /**
     * The next import of the range deletes the rejected rows with a date in the range, and the
     * rejected rows with no date of an earlier import whose whole range is in the new range.
     */
    public function testNextImportDeletesTheRejectedRowsOfTheRange(): void
    {
        $this->writeScenario('bad_values');
        $this->import(self::range('2026-01-05'));
        $this->writeScenario('base');

        $this->import(self::range('2026-01-05'));

        self::assertSame(0, $this->stored(RejectedStopRow::class));
    }

    /**
     * A rejected row with no date can be on a date outside the new range, when the earlier import
     * had a larger range. It is kept.
     */
    public function testRejectedRowWithNoDateOfALargerImportIsKept(): void
    {
        $this->writeScenario('bad_values');
        $this->import(self::range('2026-01-04', '2026-01-05'));
        $this->writeScenario('base');

        $this->import(self::range('2026-01-05'));

        [$kept] = $this->entityManager->getRepository(RejectedStopRow::class)->findAll();
        self::assertNull($kept->getServiceDate());
        self::assertSame(1, $this->stored(RejectedStopRow::class));
    }

    /**
     * The replacer writes the rows in groups, so the memory use does not grow with the file.
     */
    public function testMoreRowsThanOneGroupAreAllStored(): void
    {
        $values = (new ScenarioLoader())->load(self::SCENARIOS.'/base.yaml')->rows[0]->values;
        $rows = array_map(static fn (int $index): ExtractRow => new ExtractRow($values, 'S-'.$index), range(1, 1001));
        (new ExtractWriter())->write($this->path, $this->mapping(), $rows);

        $result = $this->import(self::range('2026-01-05'));

        self::assertSame(1001, $result->insertedRows);
        self::assertSame(1001, $this->stored(StopRow::class));
        self::assertSame(1, $this->stored(Route::class));
    }

    /**
     * @return list<ExtractRow>
     */
    private function writeScenario(string $name): array
    {
        $scenario = (new ScenarioLoader())->load(self::SCENARIOS.'/'.$name.'.yaml');
        (new ExtractWriter())->write($this->path, $this->mapping(), $scenario->rows, $scenario->omittedFields);

        return $scenario->rows;
    }

    private function import(ImportRangeDto $range): ImportResultDto
    {
        $mapping = $this->mapping();
        $batch = new ImportBatch($mapping->name, 'extract.xlsx', null, new \DateTimeImmutable(), $range);
        $this->entityManager->persist($batch);
        $this->entityManager->flush();

        $parser = self::getContainer()->get(StopRowParser::class);
        self::assertInstanceOf(StopRowParser::class, $parser);
        $replacer = self::getContainer()->get(StopRowReplacer::class);
        self::assertInstanceOf(StopRowReplacer::class, $replacer);

        return $replacer->replace($range, $mapping, $parser->parse($this->path, $mapping, $range), $batch);
    }

    private function mapping(): MappingDto
    {
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);

        return $provider->default();
    }

    /**
     * @param class-string $class
     */
    private function stored(string $class): int
    {
        return $this->entityManager->getRepository($class)->count([]);
    }

    /**
     * @return list<StopRow>
     */
    private function stopRows(): array
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(StopRow::class)->findBy([], ['id' => 'ASC']);
    }

    private static function range(string $firstDate, ?string $lastDate = null): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate ?? $firstDate), 31);
    }
}

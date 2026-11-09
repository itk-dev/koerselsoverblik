<?php

namespace App\Tests\Integration\Import;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\StopRow;
use App\Entity\User;
use App\Import\DrivingDataImporter;
use App\Import\Extract\MissingColumnsException;
use App\Import\Extract\StopRowParser;
use App\Import\ImportRangeDto;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingProvider;
use App\Import\RowIdConflictException;
use App\Import\StopRowReplacer;
use App\Repository\StopRowRepository;
use App\Repository\SubAgreementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Checks the one service for the upload page and the import command: the preview changes no data,
 * and the import replaces the rows and saves the batch in one transaction.
 */
final class DrivingDataImporterTest extends KernelTestCase
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
     * The preview counts the rows of the file and the stored rows that the import deletes.
     */
    public function testPreviewCountsAndChangesNoData(): void
    {
        $this->writeScenario('base');
        $this->importer()->import($this->path, self::range('2026-01-05'), $this->mapping(), null, 'base.xlsx');
        $this->writeScenario('range_edges');

        $preview = $this->importer()->preview($this->path, self::range('2026-01-05', '2026-01-07'), $this->mapping());

        self::assertSame(4, $preview->summary->stopRows);
        self::assertSame(1, $preview->summary->rejectedRows);
        self::assertSame(14, $preview->rowsToDelete);
        self::assertSame(['2026-01-06'], $preview->emptyDates);
        self::assertSame(14, $this->storedRows());
    }

    /**
     * The preview and the import agree on the rows with a duplicate row id.
     */
    public function testPreviewAndImportRejectTheSameDuplicateRowIds(): void
    {
        $this->writeScenario('duplicate_row_ids');

        $preview = $this->importer()->preview($this->path, self::range('2026-01-05'), $this->mapping());
        $batch = $this->importer()->import($this->path, self::range('2026-01-05'), $this->mapping(), null, 'duplicates.xlsx');

        self::assertSame(2, $preview->summary->stopRows);
        self::assertSame(1, $preview->summary->rejectedRows);
        self::assertSame(2, $batch->getRowsInserted());
        self::assertSame(1, $batch->getRowsRejected());
    }

    public function testImportSavesTheBatch(): void
    {
        $user = new User('admin@example.com');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->writeScenario('bad_values');

        $batch = $this->importer()->import($this->path, self::range('2026-01-05'), $this->mapping(), $user, 'january.xlsx');

        self::assertNotNull($batch->getId());
        self::assertSame('default', $batch->getMappingName());
        self::assertSame('january.xlsx', $batch->getFileName());
        self::assertSame($user, $batch->getUploadedBy());
        self::assertSame('2026-01-07 10:15:00', $batch->getUploadedAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-05', $batch->getFirstDate()->format('Y-m-d'));
        self::assertSame('2026-01-05', $batch->getLastDate()->format('Y-m-d'));
        self::assertSame(0, $batch->getRowsDeleted());
        self::assertSame(4, $batch->getRowsInserted());
        self::assertSame(5, $batch->getRowsRejected());
        self::assertSame(3, $batch->getWarningCount());
        self::assertCount(5, $batch->getErrors());
        // The errors are codes. They never have a value from the extract.
        $errors = json_encode($batch->getErrors(), \JSON_THROW_ON_ERROR);
        foreach (['abc', 'not-a-date', 'late', 'UNKNOWN'] as $value) {
            self::assertStringNotContainsString($value, $errors);
        }
        self::assertSame(4, $this->storedRows());
        // The counts are saved, not only set on the object.
        self::assertEquals(
            ['rows_inserted' => 4, 'rows_rejected' => 5, 'warning_count' => 3],
            $this->entityManager->getConnection()->fetchAssociative('SELECT rows_inserted, rows_rejected, warning_count FROM import_batch WHERE id = ?', [$batch->getId()]),
        );
    }

    /**
     * A user can be deleted. The batches of the user stay, with no user.
     */
    public function testDeletedUserLeavesTheBatchWithNoUser(): void
    {
        $user = new User('admin@example.com');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->writeScenario('base');
        $batch = $this->importer()->import($this->path, self::range('2026-01-05'), $this->mapping(), $user, 'base.xlsx');

        $this->entityManager->createQuery('DELETE FROM '.User::class.' user WHERE user.id = :id')
            ->setParameter('id', $user->getId())
            ->execute();

        self::assertNull($this->entityManager->getConnection()->fetchOne('SELECT uploaded_by_id FROM import_batch WHERE id = ?', [$batch->getId()]));
        self::assertSame(1, $this->storedBatches());
    }

    /**
     * The import command has no user.
     */
    public function testImportFromTheConsoleHasNoUser(): void
    {
        $this->writeScenario('base');

        $batch = $this->importer()->import($this->path, self::range('2026-01-05'), $this->mapping(), null, 'base.xlsx');

        self::assertNull($batch->getUploadedBy());
        self::assertSame(14, $batch->getRowsInserted());
    }

    /**
     * The parse starts after the delete. A file with a missing column then also undoes the delete.
     */
    public function testFailedImportLeavesTheDataAsItWas(): void
    {
        $this->writeScenario('base');
        $this->importer()->import($this->path, self::range('2026-01-05'), $this->mapping(), null, 'base.xlsx');
        $scenario = (new ScenarioLoader())->load(self::SCENARIOS.'/base.yaml');
        (new ExtractWriter())->write($this->path, $this->mapping(), $scenario->rows, [FieldName::PlannedSolo]);

        try {
            $this->importer()->import($this->path, self::range('2026-01-05'), $this->mapping(), null, 'broken.xlsx');
            self::fail('The import did not fail.');
        } catch (MissingColumnsException) {
        }

        self::assertSame(14, $this->storedRows());
        self::assertSame(1, $this->storedBatches());
    }

    /**
     * Each extract holds whole days, so a row id is not stored on a date outside the range. If it
     * is, the unique index stops the import, and no data changes. The caller gets an exception of
     * the import, with no value from the extract. The message of the database has the row id, so
     * it is not kept.
     */
    public function testRowIdThatIsStoredOutsideTheRangeStopsTheImport(): void
    {
        $this->writeScenario('base');
        $this->importer()->import($this->path, self::range('2026-01-05'), $this->mapping(), null, 'base.xlsx');
        $rows = array_map(
            static fn (ExtractRow $row): ExtractRow => new ExtractRow(['service_date' => new \DateTimeImmutable('2026-01-06')] + $row->values, $row->rowId, $row->raw, $row->kept),
            (new ScenarioLoader())->load(self::SCENARIOS.'/base.yaml')->rows,
        );
        (new ExtractWriter())->write($this->path, $this->mapping(), $rows);

        try {
            $this->importer()->import($this->path, self::range('2026-01-06'), $this->mapping(), null, 'next-day.xlsx');
            self::fail('The import did not fail.');
        } catch (RowIdConflictException $exception) {
            self::assertSame('A row id of the file is already stored on a date outside the range. The import changed no data.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame(14, $this->storedRows());
        self::assertSame(1, $this->storedBatches());
    }

    private function importer(): DrivingDataImporter
    {
        $parser = self::getContainer()->get(StopRowParser::class);
        self::assertInstanceOf(StopRowParser::class, $parser);
        $subAgreements = self::getContainer()->get(SubAgreementRepository::class);
        self::assertInstanceOf(SubAgreementRepository::class, $subAgreements);
        $stopRows = $this->entityManager->getRepository(StopRow::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);

        // The container removes a private service that no other service uses yet.
        return new DrivingDataImporter(
            $parser,
            new StopRowReplacer($this->entityManager, $subAgreements),
            $stopRows,
            $this->entityManager,
            new MockClock('2026-01-07 10:15:00'),
        );
    }

    private function writeScenario(string $name): void
    {
        $scenario = (new ScenarioLoader())->load(self::SCENARIOS.'/'.$name.'.yaml');
        (new ExtractWriter())->write($this->path, $this->mapping(), $scenario->rows, $scenario->omittedFields);
    }

    private function mapping(): MappingDto
    {
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);

        return $provider->default();
    }

    /**
     * ORM closes the entity manager after a failed transaction, so the count uses the connection.
     */
    private function storedRows(): int
    {
        return $this->countRows('stop_row');
    }

    private function storedBatches(): int
    {
        return $this->countRows('import_batch');
    }

    private function countRows(string $table): int
    {
        $count = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private static function range(string $firstDate, ?string $lastDate = null): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate ?? $firstDate), 31);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Checks that the command shows a preview, asks for a confirmation, and imports with the same
 * service as the upload page. The output never has a person id or a cell value.
 */
final class DrivingDataImportCommandTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    private string $path = '';

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    public function testYesImports(): void
    {
        $this->writeScenario('base');

        $result = $this->import('2026-01-05', '2026-01-05', ['yes']);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        $display = $result->getDisplay();
        self::assertMatchesRegularExpression('/Mapping\s+default/', $display);
        self::assertMatchesRegularExpression('/First date\s+2026-01-05/', $display);
        self::assertMatchesRegularExpression('/Last date\s+2026-01-05/', $display);
        self::assertMatchesRegularExpression('/Stop rows\s+14/', $display);
        self::assertMatchesRegularExpression('/Rejected rows\s+0/', $display);
        self::assertMatchesRegularExpression('/Warnings\s+0/', $display);
        self::assertMatchesRegularExpression('/Stored rows to delete\s+0/', $display);
        self::assertStringContainsString('Replace the stop rows of the range', $display);
        self::assertStringContainsString('Imported 14 stop rows. Deleted 0 stop rows. Rejected 0 rows.', $display);
        self::assertSame(14, $this->stored('stop_row'));
        self::assertSame(4, $this->stored('route_run'));
        self::assertSame(1, $this->stored('import_batch'));
        // The output has no person id.
        self::assertStringNotContainsString('P-00', $display);
    }

    public function testNoChangesNoData(): void
    {
        $this->writeScenario('base');

        $result = $this->import('2026-01-05', '2026-01-05', ['no']);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        self::assertStringContainsString('Nothing is imported.', $result->getDisplay());
        self::assertSame(0, $this->stored('stop_row'));
        self::assertSame(0, $this->stored('import_batch'));
    }

    /**
     * The confirmation replaces data, so a key press with no answer does not import.
     */
    public function testNoAnswerChangesNoData(): void
    {
        $this->writeScenario('base');

        $result = $this->import('2026-01-05', '2026-01-05', ['']);

        self::assertStringContainsString('Nothing is imported.', $result->getDisplay());
        self::assertSame(0, $this->stored('stop_row'));
    }

    public function testNoInteractionImportsWithNoQuestion(): void
    {
        $this->writeScenario('base');

        $result = $this->import('2026-01-05', '2026-01-05', null);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        self::assertStringNotContainsString('Replace the stop rows of the range', $result->getDisplay());
        self::assertSame(14, $this->stored('stop_row'));
    }

    /**
     * A second import of the same range deletes the rows of the first import.
     */
    public function testPreviewShowsTheStoredRowsToDelete(): void
    {
        $this->writeScenario('base');
        $this->import('2026-01-05', '2026-01-05', null);

        $result = $this->import('2026-01-05', '2026-01-05', null);

        $display = $result->getDisplay();
        self::assertMatchesRegularExpression('/Stored rows to delete\s+14/', $display);
        self::assertStringContainsString('Imported 14 stop rows. Deleted 14 stop rows. Rejected 0 rows.', $display);
        self::assertSame(14, $this->stored('stop_row'));
    }

    public function testPreviewShowsTheDatesWithNoStopRows(): void
    {
        $this->writeScenario('range_edges');

        $result = $this->import('2026-01-05', '2026-01-07', ['no']);

        self::assertMatchesRegularExpression('/Dates with no stop rows\n-+\n\s+\* 2026-01-06/', $result->getDisplay());
    }

    /**
     * The rejected rows and the warnings are counted for each field and reason, with no values.
     */
    public function testRejectedRowsAndWarningsHaveNoValues(): void
    {
        $this->writeScenario('bad_values');

        $result = $this->import('2026-01-05', '2026-01-05', null);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        $display = $result->getDisplay();
        self::assertMatchesRegularExpression('/Rejected rows\s+5/', $display);
        self::assertMatchesRegularExpression('/Warnings\s+3/', $display);
        self::assertMatchesRegularExpression('/Rejected rows\n-+/', $display);
        self::assertMatchesRegularExpression('/Warnings\n-+/', $display);
        self::assertStringContainsString('Imported 4 stop rows. Deleted 0 stop rows. Rejected 5 rows.', $display);
        foreach (['abc', 'not-a-date', 'late', 'UNKNOWN', 'P-00'] as $value) {
            self::assertStringNotContainsString($value, $display);
        }
    }

    /**
     * A wrong range gives a file with no stop rows in it. The import would then only delete data.
     */
    public function testFileWithNoStopRowsInTheRangeFails(): void
    {
        $this->writeScenario('base');
        $this->import('2026-01-05', '2026-01-05', null);

        $result = $this->import('2026-02-05', '2026-02-05', null);

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertStringContainsString('The file has no stop rows in the range. Nothing is imported.', $result->getDisplay());
        self::assertSame(14, $this->stored('stop_row'));
    }

    /**
     * @param array<string, string> $dates
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidRanges')]
    public function testInvalidRangeIsRejected(array $dates, string $message): void
    {
        $this->writeScenario('base');

        $result = $this->execute(['path-to-file' => $this->path, ...$dates], null);

        self::assertSame(Command::INVALID, $result->getStatusCode());
        self::assertStringContainsString($message, $result->getDisplay());
        self::assertSame(0, $this->stored('stop_row'));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidRanges(): iterable
    {
        yield 'a first date that is not a date' => [['from-date' => '05-01-2026', 'to-date' => '2026-01-05'], 'The first date must be a valid date as YYYY-MM-DD.'];
        yield 'a first date that does not exist' => [['from-date' => '2026-02-30', 'to-date' => '2026-03-01'], 'The first date must be a valid date as YYYY-MM-DD.'];
        yield 'a last date that is not a date' => [['from-date' => '2026-01-05', 'to-date' => 'tomorrow'], 'The last date must be a valid date as YYYY-MM-DD.'];
        yield 'a last date before the first date' => [['from-date' => '2026-01-05', 'to-date' => '2026-01-04'], 'The last date is before the first date.'];
        yield 'a range that is too long' => [['from-date' => '2026-01-01', 'to-date' => '2026-02-01'], 'The range has more than 31 days.'];
    }

    public function testUnknownMappingIsRejected(): void
    {
        $this->writeScenario('base');

        $result = $this->execute(['path-to-file' => $this->path, 'from-date' => '2026-01-05', 'to-date' => '2026-01-05', '--mapping' => 'does-not-exist'], null);

        self::assertSame(Command::INVALID, $result->getStatusCode());
        self::assertStringContainsString('does-not-exist', $result->getDisplay());
        self::assertSame(0, $this->stored('stop_row'));
    }

    public function testMappingOption(): void
    {
        $this->writeScenario('base');

        $result = $this->execute(['path-to-file' => $this->path, 'from-date' => '2026-01-05', 'to-date' => '2026-01-05', '--mapping' => 'default'], null);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        self::assertSame(14, $this->stored('stop_row'));
    }

    public function testMissingFileFails(): void
    {
        $result = $this->import('2026-01-05', '2026-01-05', null);

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertStringContainsString('The extract is not a file.', $result->getDisplay());
    }

    public function testMissingColumnFails(): void
    {
        $scenario = new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml');
        new ExtractWriter()->write($this->path, $this->mapping(), $scenario->rows, [FieldName::PlannedSolo]);

        $result = $this->import('2026-01-05', '2026-01-05', null);

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertStringContainsString('The file does not have these columns of the mapping:', $result->getDisplay());
        self::assertStringContainsString('kørselstype', $result->getDisplay());
        self::assertSame(0, $this->stored('stop_row'));
    }

    /**
     * A row id that is stored on a date outside the range stops the import, and no data changes.
     */
    public function testRowIdThatIsStoredOutsideTheRangeFails(): void
    {
        $this->writeScenario('base');
        $this->import('2026-01-05', '2026-01-05', null);
        $rows = array_map(
            static fn (ExtractRow $row): ExtractRow => new ExtractRow(['service_date' => new \DateTimeImmutable('2026-01-06')] + $row->values, $row->rowId, $row->raw, $row->kept),
            new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml')->rows,
        );
        new ExtractWriter()->write($this->path, $this->mapping(), $rows);

        $result = $this->import('2026-01-06', '2026-01-06', null);

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertStringContainsString('A row id of the file is already stored on a date outside the range.', $result->getDisplay());
        self::assertSame(14, $this->stored('stop_row'));
    }

    /**
     * @param ?list<string> $answers the answers to the questions, or null for --no-interaction
     */
    private function import(string $fromDate, string $toDate, ?array $answers): CommandTester
    {
        return $this->execute(['path-to-file' => $this->path, 'from-date' => $fromDate, 'to-date' => $toDate], $answers);
    }

    /**
     * runCommand() writes to a TestOutput, and SymfonyStyle::table() cannot write to it. Thus the
     * test uses CommandTester::execute(), which writes to a StreamOutput.
     *
     * @param array<string, string> $input
     * @param ?list<string>         $answers the answers to the questions, or null for --no-interaction
     */
    private function execute(array $input, ?array $answers): CommandTester
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('app:driving-data:import'));
        $tester->setInputs($answers ?? []);
        $tester->execute($input, ['interactive' => null !== $answers]);

        return $tester;
    }

    private function writeScenario(string $name): void
    {
        $scenario = new ScenarioLoader()->load(self::SCENARIOS.'/'.$name.'.yaml');
        new ExtractWriter()->write($this->path, $this->mapping(), $scenario->rows, $scenario->omittedFields);
    }

    private function mapping(): MappingDto
    {
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);

        return $provider->default();
    }

    /**
     * Counts with the connection, because ORM closes the entity manager after a failed import.
     */
    private function stored(string $table): int
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $count = $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }
}

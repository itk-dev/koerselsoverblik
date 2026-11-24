<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Tests\BaseScenarioTrait;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Checks that the command shows a preview of the read of the stored copies, asks for a
 * confirmation, and reads the copies again through the mapping. The stop rows of the tests have
 * no code of a late cancellation, as rows that were imported before the field existed.
 */
final class DrivingDataReadCopiesCommandTest extends KernelTestCase
{
    use BaseScenarioTrait;

    private TestHandler $log;

    protected function setUp(): void
    {
        $this->importBaseOn('2026-01-05');
        $this->importBaseOn('2026-01-06');
        $this->update('UPDATE stop_row SET cancelled_in_time = NULL');
        $logger = self::getContainer()->get('monolog.logger.driving_data_audit');
        self::assertInstanceOf(Logger::class, $logger);
        $this->log = new TestHandler();
        $logger->pushHandler($this->log);
    }

    public function testYesReads(): void
    {
        $result = $this->read('2026-01-01', '2026-01-31', ['yes']);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        $display = $result->getDisplay();
        self::assertMatchesRegularExpression('/Mapping\s+default/', $display);
        self::assertMatchesRegularExpression('/First date\s+2026-01-01/', $display);
        self::assertMatchesRegularExpression('/Last date\s+2026-01-31/', $display);
        self::assertMatchesRegularExpression('/Dates with stored rows\s+2/', $display);
        self::assertMatchesRegularExpression('/Stored stop rows to replace\s+28/', $display);
        self::assertMatchesRegularExpression('/Stored rejected rows to replace\s+0/', $display);
        self::assertMatchesRegularExpression('/Stop rows\s+28/', $display);
        self::assertMatchesRegularExpression('/Rejected rows\s+0/', $display);
        self::assertMatchesRegularExpression('/Warnings\s+0/', $display);
        self::assertStringContainsString('Replace the stored rows of 2 dates with the rows that their copies give?', $display);
        self::assertStringContainsString('Read the copies of 2 dates again: 28 stop rows and 0 rejected rows.', $display);
        // The read fills the new field from the copies.
        self::assertSame(28, $this->stored('stop_row WHERE cancelled_in_time IS NOT NULL'));
        self::assertSame('console read the stored copies from 2026-01-01 to 2026-01-31 again through the mapping "default": 2 dates, 28 stop rows and 0 rejected rows', $this->log->getRecords()[0]->message);
        // The output has no person id.
        self::assertStringNotContainsString('P-00', $display);
    }

    public function testNoChangesNoData(): void
    {
        $result = $this->read('2026-01-01', '2026-01-31', ['no']);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        self::assertStringContainsString('Nothing is changed.', $result->getDisplay());
        self::assertSame(0, $this->stored('stop_row WHERE cancelled_in_time IS NOT NULL'));
        self::assertSame([], $this->log->getRecords());
    }

    /**
     * The read replaces data, so a key press with no answer does not read.
     */
    public function testNoAnswerChangesNoData(): void
    {
        $result = $this->read('2026-01-01', '2026-01-31', ['']);

        self::assertStringContainsString('Nothing is changed.', $result->getDisplay());
        self::assertSame(0, $this->stored('stop_row WHERE cancelled_in_time IS NOT NULL'));
    }

    /**
     * A range can be longer than the maximum range of an upload, because the read works through
     * one date at a time.
     */
    public function testNoInteractionReadsWithNoQuestion(): void
    {
        $result = $this->read('2025-12-01', '2026-02-28', null);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        self::assertStringNotContainsString('Replace the stored rows', $result->getDisplay());
        self::assertSame(28, $this->stored('stop_row WHERE cancelled_in_time IS NOT NULL'));
    }

    public function testRangeWithNoStoredRowsChangesNothing(): void
    {
        $result = $this->read('2026-02-01', '2026-02-28', null);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        self::assertStringContainsString('No date in the range has stored rows. Nothing is changed.', $result->getDisplay());
        self::assertSame([], $this->log->getRecords());
    }

    /**
     * A column of a required field that no copy has stops the command before any change.
     */
    public function testMissingRequiredColumnFails(): void
    {
        $this->update("UPDATE source_row SET source_values = JSON_REMOVE(source_values, '$.RouteName')");

        $result = $this->read('2026-01-01', '2026-01-31', null);

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertStringContainsString('No copy has these columns of the mapping. Nothing is changed.', $result->getDisplay());
        self::assertStringContainsString('RouteName', $result->getDisplay());
        self::assertSame(0, $this->stored('stop_row WHERE cancelled_in_time IS NOT NULL'));
    }

    /**
     * A column of an optional field that no copy has gives a warning. The field is empty, and the
     * read continues.
     */
    public function testMissingOptionalColumnIsAWarning(): void
    {
        $this->update("UPDATE source_row SET source_values = JSON_REMOVE(source_values, '$.Placename')");

        $result = $this->read('2026-01-01', '2026-01-31', null);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        self::assertStringContainsString('No copy has these columns of optional fields. The fields get no value: * Placename', self::text($result));
        self::assertSame(0, $this->stored('stop_row WHERE location_name IS NOT NULL'));
    }

    /**
     * A copy that gives another date would stop the read at its date. The preview finds it, so the
     * command stops before any change.
     */
    public function testCopyWithAnotherDateFails(): void
    {
        $this->update("UPDATE source_row SET source_values = JSON_SET(source_values, '$.Date', '2026-01-07') WHERE id = (SELECT MAX(source_row_id) FROM stop_row WHERE service_date = '2026-01-06')");

        $result = $this->read('2026-01-01', '2026-01-31', null);

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertStringContainsString('Some copies give another date through the mapping than the date of their row. Nothing is changed.', self::text($result));
        self::assertMatchesRegularExpression('/service_date\s+outside_range\s+1\s+14/', $result->getDisplay());
        self::assertSame(0, $this->stored('stop_row WHERE cancelled_in_time IS NOT NULL'));
    }

    /**
     * A stop of the read gives the reason. The dates before it are read. A row id that is stored
     * on another date is not in the preview.
     */
    public function testStopOfTheReadFails(): void
    {
        $this->update("UPDATE source_row SET source_values = JSON_SET(source_values, '$.RouteStopID', '2026-01-05-0') WHERE id = (SELECT MAX(source_row_id) FROM stop_row WHERE service_date = '2026-01-06')");

        $result = $this->read('2026-01-01', '2026-01-31', null);

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertStringContainsString('stored on another date', self::text($result));
        self::assertSame(14, $this->stored('stop_row WHERE cancelled_in_time IS NOT NULL'));
    }

    /**
     * A date whose copies give no stop rows through the mapping keeps its rows. The read stops
     * there, and gives the reason.
     */
    public function testDateWithNoStopRowsFails(): void
    {
        $this->update("UPDATE source_row SET source_values = JSON_SET(source_values, '$.Status', 'Other') WHERE id IN (SELECT source_row_id FROM stop_row WHERE service_date = '2026-01-06')");

        $result = $this->read('2026-01-01', '2026-01-31', null);

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertStringContainsString('The copies of 2026-01-06 give no stop rows through the mapping. The date keeps its rows.', self::text($result));
        self::assertSame(28, $this->stored('stop_row'));
        self::assertSame(14, $this->stored('stop_row WHERE cancelled_in_time IS NOT NULL'));
    }

    /**
     * The console wraps the long lines, so the tests compare the text with single spaces.
     */
    private static function text(CommandTester $result): string
    {
        return (string) preg_replace('/\s+/', ' ', $result->getDisplay());
    }

    /**
     * @param array{first-date: string, last-date: string} $dates
     */
    #[DataProvider('invalidRanges')]
    public function testInvalidRangeIsRejected(array $dates, string $message): void
    {
        $result = $this->execute($dates, null);

        self::assertSame(Command::INVALID, $result->getStatusCode());
        self::assertStringContainsString($message, $result->getDisplay());
    }

    /**
     * @return iterable<string, array{array{first-date: string, last-date: string}, string}>
     */
    public static function invalidRanges(): iterable
    {
        yield 'a first date that is not a date' => [['first-date' => '05-01-2026', 'last-date' => '2026-01-05'], 'The first date must be a valid date as YYYY-MM-DD.'];
        yield 'a last date that does not exist' => [['first-date' => '2026-02-27', 'last-date' => '2026-02-30'], 'The last date must be a valid date as YYYY-MM-DD.'];
        yield 'a last date before the first date' => [['first-date' => '2026-01-06', 'last-date' => '2026-01-05'], 'The last date is before the first date.'];
    }

    public function testUnknownMappingIsRejected(): void
    {
        $result = $this->execute(['first-date' => '2026-01-01', 'last-date' => '2026-01-31', '--mapping' => 'unknown'], null);

        self::assertSame(Command::INVALID, $result->getStatusCode());
    }

    /**
     * @param ?list<string> $answers the answers to the questions, or null for --no-interaction
     */
    private function read(string $firstDate, string $lastDate, ?array $answers): CommandTester
    {
        return $this->execute(['first-date' => $firstDate, 'last-date' => $lastDate], $answers);
    }

    /**
     * The command runs in the kernel of the test, so that the log handler of the test gets the
     * record.
     *
     * @param array<string, string> $input
     * @param ?list<string>         $answers the answers to the questions, or null for --no-interaction
     */
    private function execute(array $input, ?array $answers): CommandTester
    {
        self::assertNotNull(static::$kernel);
        $tester = new CommandTester(new Application(static::$kernel)->find('app:driving-data:read-copies'));
        $tester->setInputs($answers ?? []);
        $tester->execute($input, ['interactive' => null !== $answers]);

        return $tester;
    }

    private function update(string $sql): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->getConnection()->executeStatement($sql);
    }

    /**
     * @param string $from a table, with an optional condition
     */
    private function stored(string $from): int
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $count = $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$from);
        self::assertIsNumeric($count);

        return (int) $count;
    }
}

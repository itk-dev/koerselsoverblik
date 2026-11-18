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
 * Checks that the command shows the counts, asks for a confirmation, and clears with the same
 * service as the upload page.
 */
final class DrivingDataClearCommandTest extends KernelTestCase
{
    use BaseScenarioTrait;

    private TestHandler $log;

    protected function setUp(): void
    {
        $this->importBaseOn('2026-01-05');
        $this->importBaseOn('2026-01-06');
        $logger = self::getContainer()->get('monolog.logger.driving_data_audit');
        self::assertInstanceOf(Logger::class, $logger);
        $this->log = new TestHandler();
        $logger->pushHandler($this->log);
    }

    public function testYesClears(): void
    {
        $result = $this->clear('2026-01-05', '2026-01-05', ['yes']);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        $display = $result->getDisplay();
        self::assertMatchesRegularExpression('/First date\s+2026-01-05/', $display);
        self::assertMatchesRegularExpression('/Last date\s+2026-01-05/', $display);
        self::assertMatchesRegularExpression('/Stop rows\s+14/', $display);
        self::assertMatchesRegularExpression('/Route runs\s+4/', $display);
        self::assertMatchesRegularExpression('/Rejected rows\s+0/', $display);
        self::assertStringContainsString('Clear the driving data from 2026-01-05 to 2026-01-05?', $display);
        self::assertStringContainsString('Cleared 14 stop rows, 4 route runs and 0 rejected rows.', $display);
        self::assertSame(14, $this->stored('stop_row'));
        self::assertSame(4, $this->stored('route_run'));
        self::assertSame(2, $this->stored('import_batch'));
        self::assertSame('console cleared the driving data from 2026-01-05 to 2026-01-05: 14 stop rows, 4 route runs and 0 rejected rows', $this->log->getRecords()[0]->message);
        // The output has no person id.
        self::assertStringNotContainsString('P-00', $display);
    }

    public function testNoChangesNoData(): void
    {
        $result = $this->clear('2026-01-05', '2026-01-05', ['no']);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        self::assertStringContainsString('Nothing is cleared.', $result->getDisplay());
        self::assertSame(28, $this->stored('stop_row'));
        self::assertSame([], $this->log->getRecords());
    }

    /**
     * The clear deletes data, so a key press with no answer does not clear.
     */
    public function testNoAnswerChangesNoData(): void
    {
        $result = $this->clear('2026-01-05', '2026-01-05', ['']);

        self::assertStringContainsString('Nothing is cleared.', $result->getDisplay());
        self::assertSame(28, $this->stored('stop_row'));
    }

    public function testNoInteractionClearsTheRangeWithNoQuestion(): void
    {
        $result = $this->clear('2026-01-05', '2026-01-06', null);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        self::assertStringNotContainsString('Clear the driving data', $result->getDisplay());
        self::assertStringContainsString('Cleared 28 stop rows, 8 route runs and 0 rejected rows.', $result->getDisplay());
        self::assertSame(0, $this->stored('stop_row'));
    }

    /**
     * @param array{from-date: string, to-date: string} $dates
     */
    #[DataProvider('invalidRanges')]
    public function testInvalidRangeIsRejected(array $dates, string $message): void
    {
        $result = $this->execute($dates, null);

        self::assertSame(Command::INVALID, $result->getStatusCode());
        self::assertStringContainsString($message, $result->getDisplay());
        self::assertSame(28, $this->stored('stop_row'));
    }

    /**
     * @return iterable<string, array{array{from-date: string, to-date: string}, string}>
     */
    public static function invalidRanges(): iterable
    {
        yield 'a first date that is not a date' => [['from-date' => '05-01-2026', 'to-date' => '2026-01-05'], 'The first date must be a valid date as YYYY-MM-DD.'];
        yield 'a last date that does not exist' => [['from-date' => '2026-02-27', 'to-date' => '2026-02-30'], 'The last date must be a valid date as YYYY-MM-DD.'];
        yield 'a last date before the first date' => [['from-date' => '2026-01-06', 'to-date' => '2026-01-05'], 'The last date is before the first date.'];
        yield 'a range that is too long' => [['from-date' => '2026-01-01', 'to-date' => '2026-01-08'], 'The range has more than 7 days.'];
    }

    /**
     * @param ?list<string> $answers the answers to the questions, or null for --no-interaction
     */
    private function clear(string $fromDate, string $toDate, ?array $answers): CommandTester
    {
        return $this->execute(['from-date' => $fromDate, 'to-date' => $toDate], $answers);
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
        $tester = new CommandTester(new Application(static::$kernel)->find('app:driving-data:clear'));
        $tester->setInputs($answers ?? []);
        $tester->execute($input, ['interactive' => null !== $answers]);

        return $tester;
    }

    private function stored(string $table): int
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $count = $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }
}

<?php

namespace App\Tests\Integration\Command;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Checks that the command validates a mapping against a sample file, and never shows a person id
 * or a value of a rejected row.
 */
final class DrivingDataMappingValidateCommandTest extends KernelTestCase
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

    public function testFileThatAgreesWithTheMapping(): void
    {
        $this->writeScenario('base');

        $result = $this->validate();

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        $display = $result->getDisplay();
        self::assertMatchesRegularExpression('/Stop rows\s+14/', $display);
        self::assertMatchesRegularExpression('/Rejected rows\s+0/', $display);
        self::assertMatchesRegularExpression('/First date\s+2026-01-05/', $display);
        self::assertMatchesRegularExpression('/Last date\s+2026-01-05/', $display);
        self::assertMatchesRegularExpression('/Dates with stop rows\s+1/', $display);
        self::assertMatchesRegularExpression('/Preview\n-+/', $display);
        self::assertMatchesRegularExpression('/row\s+row key\s+route\s+person_id\s+service_date/', $display);
        // The first stop is in row 4, and has the row id 1.
        self::assertMatchesRegularExpression('/4\s+1\s+R-01\s+\(hidden\)\s+2026-01-05/', $display);
        self::assertSame(5, substr_count($display, '(hidden)'));
        self::assertStringNotContainsString('P-00', $display);
        self::assertStringContainsString('The file agrees with the mapping.', $display);
    }

    /**
     * The preview shows an empty optional value as an empty cell.
     */
    public function testPreviewOfARowWithoutActualTime(): void
    {
        $values = (new ScenarioLoader())->load(self::SCENARIOS.'/base.yaml')->rows[0]->values;
        $values['actual_time'] = null;
        (new ExtractWriter())->write($this->path, self::defaultMapping(), [new ExtractRow($values)]);

        $result = $this->validate();

        self::assertSame(Command::SUCCESS, $result->getStatusCode(), $result->getDisplay());
        // The planned time comes directly before the solo value, because the actual time is empty.
        self::assertMatchesRegularExpression('/2026-01-05 07:00:00\s+no\s*$/m', $result->getDisplay());
    }

    public function testRejectedRowsAreCountedWithoutTheirValues(): void
    {
        $this->writeScenario('bad_values');

        $result = $this->validate();

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        $display = $result->getDisplay();
        self::assertMatchesRegularExpression('/Rejected rows\s+8/', $display);
        self::assertMatchesRegularExpression('/Rejected rows\n-+/', $display);
        self::assertMatchesRegularExpression('/field\s+reason\s+rows\s+first row numbers/', $display);
        self::assertStringContainsString('The file has 8 rejected rows.', $display);
        foreach ([
            'route\s+empty\s+1\s+5',
            'run\s+not_whole_number\s+1\s+6',
            'next_distance_km\s+not_number\s+1\s+7',
            'planned_solo\s+unknown_bool_value\s+1\s+8',
            'service_date\s+not_date\s+1\s+9',
            'planned_time\s+not_time\s+1\s+10',
            'actual_time\s+not_date_time\s+1\s+11',
            'stop_type\s+unknown_code\s+1\s+12',
        ] as $line) {
            self::assertMatchesRegularExpression('/'.$line.'/', $display);
        }
        foreach (['P-00', 'abc', 'far', 'maybe', 'not-a-date', 'late', 'soon', 'UNKNOWN'] as $value) {
            self::assertStringNotContainsString($value, $display);
        }
    }

    public function testAllMissingColumnsAreShown(): void
    {
        (new ExtractWriter())->write($this->path, self::defaultMapping(), (new ScenarioLoader())->load(self::SCENARIOS.'/base.yaml')->rows, [FieldName::Run, FieldName::StopType]);

        $result = $this->validate();

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertStringContainsString('The file does not have these columns of the mapping', $result->getDisplay());
        self::assertStringContainsString('blocknumber', $result->getDisplay());
        self::assertStringContainsString('StopType', $result->getDisplay());
    }

    public function testFileWithoutStopRowsFails(): void
    {
        (new ExtractWriter())->write($this->path, self::defaultMapping(), []);

        $result = $this->validate();

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertStringContainsString('no stop rows', $result->getDisplay());
    }

    public function testMissingFileFails(): void
    {
        $result = $this->validate();

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertStringContainsString('The extract is not a file.', $result->getDisplay());
    }

    public function testUnknownMappingIsInvalid(): void
    {
        $this->writeScenario('base');

        $result = self::execute(['mapping-name' => 'does-not-exist', 'path-to-file' => $this->path]);

        self::assertSame(Command::INVALID, $result->getStatusCode());
        self::assertStringContainsString('does-not-exist', $result->getDisplay());
    }

    public function testRowsOptionSetsTheSizeOfThePreview(): void
    {
        $this->writeScenario('base');

        $result = $this->validate(['--rows' => '2']);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        self::assertSame(2, substr_count($result->getDisplay(), '(hidden)'));

        $result = $this->validate(['--rows' => '1']);

        self::assertSame(Command::SUCCESS, $result->getStatusCode());
        self::assertSame(1, substr_count($result->getDisplay(), '(hidden)'));
    }

    public function testRowsBelowOneIsInvalid(): void
    {
        $result = $this->validate(['--rows' => '0']);

        self::assertSame(Command::INVALID, $result->getStatusCode());
        self::assertStringContainsString('at least 1', $result->getDisplay());
    }

    /**
     * @param array<string, string> $options
     */
    private function validate(array $options = []): CommandTester
    {
        return self::execute(['mapping-name' => 'default', 'path-to-file' => $this->path, ...$options]);
    }

    /**
     * runCommand() writes to a TestOutput, and SymfonyStyle::table() cannot write to it. Thus the
     * test uses CommandTester::execute(), which writes to a StreamOutput.
     *
     * @param array<string, string> $input
     */
    private static function execute(array $input): CommandTester
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('app:driving-data:mapping:validate'));
        $tester->execute($input);

        return $tester;
    }

    private function writeScenario(string $name): void
    {
        $scenario = (new ScenarioLoader())->load(self::SCENARIOS.'/'.$name.'.yaml');
        (new ExtractWriter())->write($this->path, self::defaultMapping(), $scenario->rows, $scenario->omittedFields);
    }

    private static function defaultMapping(): MappingDto
    {
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);

        return $provider->default();
    }
}

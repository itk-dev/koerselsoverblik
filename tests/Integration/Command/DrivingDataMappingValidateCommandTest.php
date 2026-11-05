<?php

namespace App\Tests\Integration\Command;

use App\Command\DrivingDataMappingValidateCommand;
use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Import\Extract\StopRowParser;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingLoader;
use App\Import\Mapping\MappingProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Checks that the command validates a mapping against a sample file, and never shows a person id
 * or a value of a rejected row.
 */
final class DrivingDataMappingValidateCommandTest extends KernelTestCase
{
    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    private const string DEFAULT_MAPPING = __DIR__.'/../../../config/import/driving_data/default.yaml';

    private string $path = '';

    private string $mappingDirectory = '';

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        if (is_dir($this->mappingDirectory)) {
            foreach (glob($this->mappingDirectory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->mappingDirectory);
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
        // The times are in APP_TIMEZONE, as in the file.
        self::assertMatchesRegularExpression('/2026-01-05 07:00:00\s+2026-01-05 07:02:00/', $display);
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

    /**
     * A row id column can be the person id column. Then the row key has the person id, and the
     * preview hides the row key.
     */
    public function testRowKeyWithThePersonIdIsHidden(): void
    {
        $this->mappingDirectory = sys_get_temp_dir().'/'.uniqid('mapping_dir_', true);
        mkdir($this->mappingDirectory);
        $yaml = (string) file_get_contents(self::DEFAULT_MAPPING);
        file_put_contents($this->mappingDirectory.'/person_key.yaml', str_replace('row_id: [RouteStopID]', 'row_id: [PersonID, Date]', $yaml));
        $mappings = new MappingProvider(new MappingLoader(), $this->mappingDirectory, 'person_key');
        (new ExtractWriter())->write($this->path, $mappings->default(), (new ScenarioLoader())->load(self::SCENARIOS.'/base.yaml')->rows);
        $parser = self::getContainer()->get(StopRowParser::class);
        self::assertInstanceOf(StopRowParser::class, $parser);
        $application = new ConsoleApplication();
        $application->addCommand(new DrivingDataMappingValidateCommand($mappings, $parser, 'Europe/Copenhagen'));
        $tester = new CommandTester($application->find('app:driving-data:mapping:validate'));

        $tester->execute(['mapping-name' => 'person_key', 'path-to-file' => $this->path]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertMatchesRegularExpression('/4\s+\(hidden\)\s+R-01\s+\(hidden\)\s+2026-01-05/', $tester->getDisplay());
        self::assertStringNotContainsString('P-00', $tester->getDisplay());
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

    /**
     * A rejected row without a row id has no field. The table shows "(row id)" for it.
     */
    public function testRowWithoutRowIdIsRejected(): void
    {
        $values = (new ScenarioLoader())->load(self::SCENARIOS.'/base.yaml')->rows[0]->values;
        (new ExtractWriter())->write($this->path, self::defaultMapping(), [new ExtractRow($values, '')]);

        $result = $this->validate();

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        self::assertMatchesRegularExpression('/\(row id\)\s+invalid_row_id\s+1\s+4/', $result->getDisplay());
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

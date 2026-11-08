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

    private const string CUSTOM_MAPPING = 'custom';

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
        self::assertMatchesRegularExpression('/Warnings\s+0/', $display);
        self::assertMatchesRegularExpression('/First date\s+2026-01-05/', $display);
        self::assertMatchesRegularExpression('/Last date\s+2026-01-05/', $display);
        self::assertMatchesRegularExpression('/Dates with stop rows\s+1/', $display);
        self::assertStringNotContainsString('Kept columns that are not in the file', $display);
        self::assertStringNotContainsString('Excluded columns that are not in the file', $display);
        self::assertStringNotContainsString('Columns that are not read', $display);
        // No section for the rejected rows or the warnings when there are none.
        self::assertDoesNotMatchRegularExpression('/(Rejected rows|Warnings)\n-+/', $display);
        self::assertMatchesRegularExpression('/Preview\n-+/', $display);
        self::assertMatchesRegularExpression('/row\s+row key\s+route\s+person_id\s+service_date/', $display);
        // The first stop is in row 4, and has the row id 1.
        self::assertMatchesRegularExpression('/4\s+1\s+R-01\s+\(hidden\)\s+2026-01-05/', $display);
        // The times are wall-clock times, as in the file.
        self::assertMatchesRegularExpression('/2026-01-05 07:00:00\s+2026-01-05 07:02:00/', $display);
        self::assertSame(5, substr_count($display, '(hidden)'));
        self::assertStringNotContainsString('P-00', $display);
        self::assertStringContainsString('The file agrees with the mapping.', $display);
    }

    /**
     * The command has no range, so it also accepts the row after the range of the scenario.
     */
    public function testFirstAndLastDateOfAFileWithManyDates(): void
    {
        $this->writeScenario('range_edges');

        $result = $this->validate();

        self::assertSame(Command::SUCCESS, $result->getStatusCode(), $result->getDisplay());
        $display = $result->getDisplay();
        self::assertMatchesRegularExpression('/First date\s+2026-01-05/', $display);
        self::assertMatchesRegularExpression('/Last date\s+2026-01-08/', $display);
        self::assertMatchesRegularExpression('/Dates with stop rows\s+3/', $display);
    }

    /**
     * The solo value is optional in the default mapping.
     */
    public function testPreviewOfARowWithoutPlannedSolo(): void
    {
        $values = (new ScenarioLoader())->load(self::SCENARIOS.'/base.yaml')->rows[0]->values;
        $values['planned_solo'] = null;
        (new ExtractWriter())->write($this->path, self::defaultMapping(), [new ExtractRow($values)]);

        $result = $this->validate();

        self::assertSame(Command::SUCCESS, $result->getStatusCode(), $result->getDisplay());
        // The actual time is the last value, because the solo value is empty.
        self::assertMatchesRegularExpression('/2026-01-05 07:02:00\s*$/m', $result->getDisplay());
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
        $mapping = $this->customMapping('row_id: [RouteStopID]', 'row_id: [PersonID, Date]');
        (new ExtractWriter())->write($this->path, $mapping, (new ScenarioLoader())->load(self::SCENARIOS.'/base.yaml')->rows);

        $result = $this->validateCustom();

        self::assertSame(Command::SUCCESS, $result->getStatusCode(), $result->getDisplay());
        self::assertMatchesRegularExpression('/4\s+\(hidden\)\s+R-01\s+\(hidden\)\s+2026-01-05/', $result->getDisplay());
        self::assertStringNotContainsString('P-00', $result->getDisplay());
    }

    public function testRejectedRowsAreCountedWithoutTheirValues(): void
    {
        $this->writeScenario('bad_values');

        $result = $this->validate();

        self::assertSame(Command::FAILURE, $result->getStatusCode());
        $display = $result->getDisplay();
        self::assertMatchesRegularExpression('/Rejected rows\s+5/', $display);
        self::assertMatchesRegularExpression('/Rejected rows\n-+/', $display);
        self::assertMatchesRegularExpression('/field\s+reason\s+rows\s+first row numbers/', $display);
        self::assertStringContainsString('The file has 5 rejected rows.', $display);
        foreach ([
            'route\s+empty\s+1\s+5',
            'run\s+not_whole_number\s+1\s+6',
            'service_date\s+not_date\s+1\s+9',
            'planned_time\s+not_time\s+1\s+10',
            'stop_type\s+unknown_code\s+1\s+12',
        ] as $line) {
            self::assertMatchesRegularExpression('/'.$line.'/', $display);
        }
        foreach (['P-00', 'abc', 'far', 'maybe', 'not-a-date', 'late', 'soon', 'UNKNOWN'] as $value) {
            self::assertStringNotContainsString($value, $display);
        }
    }

    /**
     * A bad value in an optional field gives a warning. The command shows the field and the
     * reason, but never the value.
     */
    public function testWarningsAreCountedWithoutTheirValues(): void
    {
        $this->writeScenario('bad_values');

        $display = $this->validate()->getDisplay();

        self::assertMatchesRegularExpression('/Warnings\s+3/', $display);
        self::assertMatchesRegularExpression('/Warnings\n-+/', $display);
        foreach ([
            'next_distance_km\s+not_number\s+1\s+7',
            'planned_solo\s+unknown_bool_value\s+1\s+8',
            'actual_time\s+not_date_time\s+1\s+11',
        ] as $line) {
            self::assertMatchesRegularExpression('/'.$line.'/', $display);
        }
        foreach (['far', 'maybe', 'soon'] as $value) {
            self::assertStringNotContainsString($value, $display);
        }
    }

    /**
     * The command names the kept columns that the file does not have, and the columns of the file
     * that the mapping does not read. They are not an error.
     */
    public function testColumnsThatAreMissingOrNotRead(): void
    {
        $this->customMapping(
            ['row_id: [RouteStopID]', "    - Tur\n"],
            ['row_id: [blocknumber]', "    - Tur\n    - Note\n    - Trip\n"],
        );
        (new ExtractWriter())->write($this->path, self::defaultMapping(), (new ScenarioLoader())->load(self::SCENARIOS.'/base.yaml')->rows);

        $result = $this->validateCustom();

        self::assertSame(Command::SUCCESS, $result->getStatusCode(), $result->getDisplay());
        $display = $result->getDisplay();
        self::assertMatchesRegularExpression('/Kept columns that are not in the file\n-+\n\n\s+\* Note\n\s+\* Trip/', $display);
        self::assertMatchesRegularExpression('/Columns that are not read\n-+\n\n\s+\* RouteStopID/', $display);
    }

    /**
     * An excluded column is not a column that is not read. The command names the excluded columns
     * that the file does not have, because such a column can have a new name in the file.
     */
    public function testExcludedColumns(): void
    {
        $this->customMapping(
            ['row_id: [RouteStopID]', 'exclude_columns: [PersonName,'],
            ['row_id: [blocknumber]', 'exclude_columns: [RouteStopID, Ghost, PersonName,'],
        );
        $this->writeScenario('base');

        $result = $this->validateCustom();

        self::assertSame(Command::SUCCESS, $result->getStatusCode(), $result->getDisplay());
        $display = $result->getDisplay();
        self::assertMatchesRegularExpression('/Excluded columns that are not in the file\n-+\n\n\s+\* Ghost\n\n/', $display);
        self::assertStringNotContainsString('Columns that are not read', $display);
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

    /**
     * Writes a copy of the default mapping with changes to a temporary mapping directory.
     *
     * @param string|list<string> $search
     * @param string|list<string> $replace the replacements, in the order of $search
     */
    private function customMapping(string|array $search, string|array $replace): MappingDto
    {
        $yaml = (string) file_get_contents(self::DEFAULT_MAPPING);
        foreach ((array) $search as $text) {
            self::assertStringContainsString($text, $yaml);
        }
        $this->mappingDirectory = sys_get_temp_dir().'/'.uniqid('mapping_dir_', true);
        mkdir($this->mappingDirectory);
        file_put_contents($this->mappingDirectory.'/'.self::CUSTOM_MAPPING.'.yaml', str_replace($search, $replace, $yaml));

        return $this->customProvider()->default();
    }

    /**
     * The command in the container reads only the mappings in config/. Thus the test makes a
     * command with the temporary mapping directory.
     */
    private function validateCustom(): CommandTester
    {
        $parser = self::getContainer()->get(StopRowParser::class);
        self::assertInstanceOf(StopRowParser::class, $parser);
        $application = new ConsoleApplication();
        $application->addCommand(new DrivingDataMappingValidateCommand($this->customProvider(), $parser));
        $tester = new CommandTester($application->find('app:driving-data:mapping:validate'));
        $tester->execute(['mapping-name' => self::CUSTOM_MAPPING, 'path-to-file' => $this->path]);

        return $tester;
    }

    private function customProvider(): MappingProvider
    {
        return new MappingProvider(new MappingLoader(), $this->mappingDirectory, self::CUSTOM_MAPPING);
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

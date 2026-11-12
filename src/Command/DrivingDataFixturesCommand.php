<?php

declare(strict_types=1);

namespace App\Command;

use App\Clock\LocalCalendar;
use App\DataFixtures\DrivingData\DatasetSettings;
use App\DataFixtures\DrivingData\DevDatasetGenerator;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Import\Mapping\MappingProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Writes the test scenarios and the development dataset as extracts in the layout of the
 * default mapping. The files contain only invented data.
 */
#[AsCommand(name: 'app:fixtures:driving-data', description: 'Write the test scenarios and the development dataset as driving data extracts')]
#[When(env: 'dev')]
#[When(env: 'test')]
final readonly class DrivingDataFixturesCommand
{
    private const int MONTHS = 13;

    public function __construct(
        private MappingProvider $mappings,
        private ScenarioLoader $scenarios,
        private DevDatasetGenerator $generator,
        private ExtractWriter $writer,
        private LocalCalendar $calendar,
        #[Autowire('%kernel.project_dir%/tests/fixtures/driving_data')]
        private string $scenarioDirectory,
        #[Autowire('%kernel.project_dir%/var/fixtures/driving_data')]
        private string $outputDirectory,
        #[Autowire(env: 'float:APP_FIXTURES_PUNCTUALITY_START')]
        private float $punctualityStart,
        #[Autowire(env: 'float:APP_FIXTURES_PUNCTUALITY_TREND')]
        private float $punctualityTrend,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('The seed of the development dataset')]
        int $seed = 1,
        #[Option('The last month of the development dataset, as YYYY-MM. The default is the current month')]
        ?string $lastMonth = null,
        #[Option('The number of months in the development dataset')]
        int $months = self::MONTHS,
        #[Option('The share in percent of the runs with an on-time last drop-off in the first month. The default is APP_FIXTURES_PUNCTUALITY_START')]
        ?float $punctualityStart = null,
        #[Option('The change of the punctuality in percentage points for each month. The default is APP_FIXTURES_PUNCTUALITY_TREND')]
        ?float $punctualityTrend = null,
    ): int {
        if ($months < 1) {
            $io->error('The number of months must be at least 1.');

            return Command::INVALID;
        }
        $punctualityStart ??= $this->punctualityStart;
        $punctualityTrend ??= $this->punctualityTrend;
        if ($punctualityStart < 0 || $punctualityStart > 100) {
            $io->error('The punctuality must be from 0 to 100.');

            return Command::INVALID;
        }

        $last = null === $lastMonth
            ? $this->calendar->today()->modify('first day of this month')
            : \DateTimeImmutable::createFromFormat('!Y-m', $lastMonth);
        if (false === $last) {
            $io->error('The last month must have the format YYYY-MM.');

            return Command::INVALID;
        }

        $mapping = $this->mappings->default();
        // Load all scenarios first, so that a scenario that is not valid stops the command before it deletes files.
        $scenarios = array_map($this->scenarios->load(...), glob($this->scenarioDirectory.'/*.yaml') ?: []);

        // Delete the files of an earlier run, so that the directory holds only the files of this run.
        is_dir($this->outputDirectory) || mkdir($this->outputDirectory, 0o777, true);
        foreach (glob($this->outputDirectory.'/*.xlsx') ?: [] as $earlier) {
            unlink($earlier);
        }

        $files = 0;
        foreach ($scenarios as $scenario) {
            $this->writer->write($this->outputDirectory.'/'.$scenario->name.'.xlsx', $mapping, $scenario->rows, $scenario->omittedFields);
            ++$files;
        }
        // One file for each ISO week, from Monday to Sunday, as a user uploads the data. The weeks
        // cover all days of the months.
        $first = $last->modify(\sprintf('-%d months', $months - 1));
        $settings = new DatasetSettings($seed, $first, $punctualityStart, $punctualityTrend);
        $end = $last->modify('first day of next month');
        for ($monday = $first->modify('monday this week'); $monday < $end; $monday = $monday->modify('+7 days')) {
            $this->writer->write($this->outputDirectory.'/dev-'.$monday->format('Y-m-d').'.xlsx', $mapping, $this->generator->week($settings, $monday));
            ++$files;
        }

        $io->text(\sprintf('Punctuality: %s %% in the first month, %+.1f percentage points for each month.', (string) round($punctualityStart, 1), $punctualityTrend));
        $io->success(\sprintf('Wrote %d files to %s.', $files, $this->outputDirectory));

        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

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
        #[Autowire('%kernel.project_dir%/tests/fixtures/driving_data')]
        private string $scenarioDirectory,
        #[Autowire('%kernel.project_dir%/var/fixtures/driving_data')]
        private string $outputDirectory,
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
    ): int {
        if ($months < 1) {
            $io->error('The number of months must be at least 1.');

            return Command::INVALID;
        }

        $last = null === $lastMonth
            ? new \DateTimeImmutable('first day of this month')
            : \DateTimeImmutable::createFromFormat('!Y-m', $lastMonth);
        if (false === $last) {
            $io->error('The last month must have the format YYYY-MM.');

            return Command::INVALID;
        }

        $mapping = $this->mappings->default();
        is_dir($this->outputDirectory) || mkdir($this->outputDirectory, 0o777, true);

        $files = 0;
        foreach (glob($this->scenarioDirectory.'/*.yaml') ?: [] as $path) {
            $scenario = $this->scenarios->load($path);
            $this->writer->write($this->outputDirectory.'/'.$scenario->name.'.xlsx', $mapping, $scenario->rows, $scenario->omittedFields);
            ++$files;
        }

        // Delete the months of an earlier run, so that the directory holds one dataset.
        foreach (glob($this->outputDirectory.'/dev-*.xlsx') ?: [] as $earlier) {
            unlink($earlier);
        }
        for ($month = $last->modify(\sprintf('-%d months', $months - 1)); $month <= $last; $month = $month->modify('+1 month')) {
            $this->writer->write($this->outputDirectory.'/dev-'.$month->format('Y-m').'.xlsx', $mapping, $this->generator->month($seed, $month));
            ++$files;
        }

        $io->success(\sprintf('Wrote %d files to %s.', $files, $this->outputDirectory));

        return Command::SUCCESS;
    }
}

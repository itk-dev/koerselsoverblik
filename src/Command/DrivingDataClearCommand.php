<?php

declare(strict_types=1);

namespace App\Command;

use App\Clock\IsoDate;
use App\Import\DrivingDataClearer;
use App\Import\ImportRangeDto;
use App\Import\InvalidImportRangeException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Clears the driving data of a date range, with the same service as the upload page. It shows the
 * counts, and asks for a confirmation before it changes data.
 */
#[AsCommand(name: 'app:driving-data:clear', description: 'Clear the driving data of a date range')]
final readonly class DrivingDataClearCommand
{
    public function __construct(
        private DrivingDataClearer $clearer,
        #[Autowire(param: 'app.driving_data_max_days')]
        private int $maxDays,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Argument('The first date of the range, as YYYY-MM-DD')]
        string $fromDate,
        #[Argument('The last date of the range, as YYYY-MM-DD')]
        string $toDate,
    ): int {
        $firstDate = IsoDate::parse($fromDate);
        if (null === $firstDate) {
            $io->error('The first date must be a valid date as YYYY-MM-DD.');

            return Command::INVALID;
        }
        $lastDate = IsoDate::parse($toDate);
        if (null === $lastDate) {
            $io->error('The last date must be a valid date as YYYY-MM-DD.');

            return Command::INVALID;
        }

        try {
            $range = ImportRangeDto::create($firstDate, $lastDate, $this->maxDays);
        } catch (InvalidImportRangeException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $counts = $this->clearer->count($range);
        $io->definitionList(
            ['First date' => $fromDate],
            ['Last date' => $toDate],
            ['Stop rows' => $counts->stopRows],
            ['Route runs' => $counts->routeRuns],
            ['Rejected rows' => $counts->rejectedRows],
        );
        if ($input->isInteractive() && !$io->confirm(\sprintf('Clear the driving data from %s to %s?', $fromDate, $toDate), false)) {
            $io->note('Nothing is cleared.');

            return Command::SUCCESS;
        }

        $cleared = $this->clearer->clear($range, null);
        $io->success(\sprintf('Cleared %d stop rows, %d route runs and %d rejected rows.', $cleared->stopRows, $cleared->routeRuns, $cleared->rejectedRows));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\DrivingDataImporter;
use App\Import\Extract\InvalidExtractException;
use App\Import\Extract\MissingColumnsException;
use App\Import\ImportPreviewDto;
use App\Import\ImportRangeDto;
use App\Import\InvalidImportRangeException;
use App\Import\Mapping\InvalidMappingException;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingProvider;
use App\Import\RowIdConflictException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Imports an extract for a date range, with the same service as the upload page. It shows a
 * preview, and asks for a confirmation before it changes data. It does not delete the file.
 *
 * The output never has a person id (ADR 004) or a cell value.
 */
#[AsCommand(name: 'app:driving-data:import', description: 'Import a driving data extract for a date range')]
final readonly class DrivingDataImportCommand
{
    public function __construct(
        private MappingProvider $mappings,
        private DrivingDataImporter $importer,
        #[Autowire(param: 'app.driving_data_max_days')]
        private int $maxDays,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Argument('The path of the extract')]
        string $pathToFile,
        #[Argument('The first date of the range, as YYYY-MM-DD')]
        string $fromDate,
        #[Argument('The last date of the range, as YYYY-MM-DD')]
        string $toDate,
        #[Option('The name of the import mapping. The default is the value of APP_DRIVING_DATA_MAPPING')]
        ?string $mapping = null,
    ): int {
        $firstDate = self::date($fromDate);
        if (null === $firstDate) {
            $io->error('The first date must be a valid date as YYYY-MM-DD.');

            return Command::INVALID;
        }
        $lastDate = self::date($toDate);
        if (null === $lastDate) {
            $io->error('The last date must be a valid date as YYYY-MM-DD.');

            return Command::INVALID;
        }

        try {
            $range = ImportRangeDto::create($firstDate, $lastDate, $this->maxDays);
            $importMapping = null === $mapping ? $this->mappings->default() : $this->mappings->get($mapping);
        } catch (InvalidImportRangeException|InvalidMappingException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        try {
            $preview = $this->importer->preview($pathToFile, $range, $importMapping, 0);
            self::showPreview($io, $range, $importMapping, $preview);

            // An import of a file with no stop rows in the range only deletes data. A wrong range
            // is the usual cause.
            if (0 === $preview->summary->stopRows) {
                $io->error('The file has no stop rows in the range. Nothing is imported.');

                return Command::FAILURE;
            }
            if ($input->isInteractive() && !$io->confirm('Replace the stop rows of the range with the rows of the file?', false)) {
                $io->note('Nothing is imported.');

                return Command::SUCCESS;
            }

            $batch = $this->importer->import($pathToFile, $range, $importMapping, null, basename($pathToFile));
        } catch (MissingColumnsException $exception) {
            $io->error('The file does not have these columns of the mapping:');
            $io->listing($exception->columns);

            return Command::FAILURE;
        } catch (InvalidExtractException|RowIdConflictException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Imported %d stop rows. Deleted %d stop rows. Rejected %d rows.',
            $batch->getRowsInserted(),
            $batch->getRowsDeleted(),
            $batch->getRowsRejected(),
        ));

        return Command::SUCCESS;
    }

    /**
     * Shows the counts and the groups of the preview. A group has a field, a reason and row
     * numbers, but no values.
     */
    private static function showPreview(SymfonyStyle $io, ImportRangeDto $range, MappingDto $mapping, ImportPreviewDto $preview): void
    {
        $io->definitionList(
            ['Mapping' => $mapping->name],
            ['First date' => $range->firstDate->format('Y-m-d')],
            ['Last date' => $range->lastDate->format('Y-m-d')],
            ['Stop rows' => $preview->summary->stopRows],
            ['Rejected rows' => $preview->summary->rejectedRows],
            ['Warnings' => $preview->summary->warningCount],
            ['Stored rows to delete' => $preview->rowsToDelete],
        );

        if ([] !== $preview->emptyDates) {
            $io->section('Dates with no stop rows');
            $io->listing($preview->emptyDates);
        }
        RejectionGroupsTable::show($io, 'Rejected rows', $preview->summary->rejections);
        // A bad value in an optional field. The field is empty, and the row is imported.
        RejectionGroupsTable::show($io, 'Warnings', $preview->summary->warnings);
    }

    /**
     * Reads a date as YYYY-MM-DD. A date that does not exist, for example 2026-02-30, is not valid.
     */
    private static function date(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Clock\IsoDate;
use App\Import\Mapping\InvalidMappingException;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingProvider;
use App\Import\RowIdConflictException;
use App\Import\StoredCopyException;
use App\Import\StoredCopyPreviewDto;
use App\Import\StoredCopyReader;
use App\Report\DateRangeDto;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reads the stored copies of the rows of a date range again through a mapping (ADR 006), for
 * example after a new field. It shows a preview, and asks for a confirmation before it changes
 * data. The range can be longer than an upload, because the read works through one date at a
 * time.
 *
 * The output never has a person id (ADR 004) or a cell value.
 */
#[AsCommand(name: 'app:driving-data:read-copies', description: 'Read the stored copies of the rows of a date range again through a mapping')]
final readonly class DrivingDataReadCopiesCommand
{
    public function __construct(
        private MappingProvider $mappings,
        private StoredCopyReader $reader,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Argument('The first date of the range, as YYYY-MM-DD')]
        string $firstDate,
        #[Argument('The last date of the range, as YYYY-MM-DD')]
        string $lastDate,
        #[Option('The name of the import mapping. The default is the value of APP_DRIVING_DATA_MAPPING')]
        ?string $mapping = null,
    ): int {
        $first = IsoDate::parse($firstDate);
        if (null === $first) {
            $io->error('The first date must be a valid date as YYYY-MM-DD.');

            return Command::INVALID;
        }
        $last = IsoDate::parse($lastDate);
        if (null === $last) {
            $io->error('The last date must be a valid date as YYYY-MM-DD.');

            return Command::INVALID;
        }

        try {
            $range = DateRangeDto::create($first, $last);
            $readMapping = null === $mapping ? $this->mappings->default() : $this->mappings->get($mapping);
        } catch (\InvalidArgumentException|InvalidMappingException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        try {
            $preview = $this->reader->preview($range, $readMapping);
        } catch (StoredCopyException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
        if ([] === $preview->dates) {
            $io->note('No date in the range has stored rows. Nothing is changed.');

            return Command::SUCCESS;
        }
        self::showPreview($io, $range, $readMapping, $preview);
        // The read does not start with a problem of the preview, so nothing is changed.
        $problem = $preview->problem();
        if (null !== $problem) {
            $io->error($problem->getMessage());

            return Command::FAILURE;
        }
        $question = \sprintf('Replace the stored rows of %d dates with the rows that their copies give?', \count($preview->dates));
        if ($input->isInteractive() && !$io->confirm($question, false)) {
            $io->note('Nothing is changed.');

            return Command::SUCCESS;
        }

        try {
            $result = $this->reader->read($range, $readMapping);
        } catch (RowIdConflictException) {
            $io->error('A copy has a row id that is stored on another date. That date keeps its rows, and the dates before it are read again.');

            return Command::FAILURE;
        } catch (StoredCopyException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Read the copies of %d dates again: %d stop rows and %d rejected rows.', $result->dates, $result->stopRows, $result->rejectedRows));

        return Command::SUCCESS;
    }

    /**
     * Shows the counts and the groups of the preview. A group has a field, a reason and row
     * numbers, but no values.
     */
    private static function showPreview(SymfonyStyle $io, DateRangeDto $range, MappingDto $mapping, StoredCopyPreviewDto $preview): void
    {
        $io->definitionList(
            ['Mapping' => $mapping->name],
            ['First date' => $range->firstDate->format('Y-m-d')],
            ['Last date' => $range->lastDate->format('Y-m-d')],
            ['Dates with stored rows' => \count($preview->dates)],
            ['Stored stop rows to replace' => $preview->storedStopRows],
            ['Stored rejected rows to replace' => $preview->storedRejectedRows],
            ['Stop rows' => $preview->summary->stopRows],
            ['Rejected rows' => $preview->summary->rejectedRows],
            ['Warnings' => $preview->summary->warningCount],
        );

        if ([] !== $preview->missingOptionalColumns) {
            $io->warning('No copy has these columns of optional fields. The fields get no value:');
            $io->listing($preview->missingOptionalColumns);
        }
        RejectionGroupsTable::show($io, 'Rejected rows', $preview->summary->rejections);
        // A bad value in an optional field. The field is empty, and the row is read.
        RejectionGroupsTable::show($io, 'Warnings', $preview->summary->warnings);
    }
}

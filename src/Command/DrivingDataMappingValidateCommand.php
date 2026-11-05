<?php

namespace App\Command;

use App\Import\Extract\ExtractSummaryDto;
use App\Import\Extract\InvalidExtractException;
use App\Import\Extract\MissingColumnsException;
use App\Import\Extract\RejectionCountDto;
use App\Import\Extract\StopRowDto;
use App\Import\Extract\StopRowParser;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\InvalidMappingException;
use App\Import\Mapping\MappingProvider;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads a sample file through an import mapping, and shows the result. It changes no data.
 *
 * The output never has a person id (ADR 004) or a value of a rejected row.
 */
#[AsCommand(name: 'app:driving-data:mapping:validate', description: 'Validate an import mapping against a sample file')]
final readonly class DrivingDataMappingValidateCommand
{
    private const string HIDDEN = '(hidden)';

    /**
     * @param string $timeZone the value of APP_TIMEZONE, the time zone of the wall-clock times in the extract
     */
    public function __construct(
        private MappingProvider $mappings,
        private StopRowParser $parser,
        #[Autowire('%app.timezone%')]
        private string $timeZone,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The name of the import mapping')]
        string $mappingName,
        #[Argument('The path of the sample file')]
        string $pathToFile,
        #[Option('The number of stop rows in the preview')]
        int $rows = 5,
    ): int {
        if ($rows < 1) {
            $io->error('The number of rows must be at least 1.');

            return Command::INVALID;
        }

        try {
            $mapping = $this->mappings->get($mappingName);
        } catch (InvalidMappingException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        try {
            // The command has no upload range, so the parser accepts all dates.
            $summary = ExtractSummaryDto::fromRows($this->parser->parse($pathToFile, $mapping, null), $rows);
        } catch (MissingColumnsException $exception) {
            $io->error('The file does not have these columns of the mapping:');
            $io->listing($exception->columns);

            return Command::FAILURE;
        } catch (InvalidExtractException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->definitionList(
            ['Stop rows' => $summary->stopRows],
            ['Rejected rows' => $summary->rejectedRows],
            ['First date' => $summary->dates[0] ?? '-'],
            ['Last date' => array_slice($summary->dates, -1)[0] ?? '-'],
            ['Dates with stop rows' => \count($summary->dates)],
        );

        if ([] !== $summary->rejections) {
            $io->section('Rejected rows');
            $io->table(
                ['field', 'reason', 'rows', 'first row numbers'],
                array_map(static fn (RejectionCountDto $group): array => [
                    $group->field->value ?? '(row id)',
                    $group->reason->value,
                    $group->count,
                    implode(', ', $group->rowNumbers),
                ], $summary->rejections),
            );
        }

        if ([] !== $summary->preview) {
            // A row id column can be the person id column. Then the row key has the person id.
            $hideRowKey = \in_array($mapping->field(FieldName::PersonId)->column, $mapping->rowId, true);
            $io->section('Preview');
            $io->table(
                ['row', 'row key', ...array_map(static fn (FieldName $name): string => $name->value, FieldName::cases())],
                array_map(fn (StopRowDto $row): array => $this->previewRow($row, $hideRowKey), $summary->preview),
            );
        }

        if ($summary->rejectedRows > 0) {
            $io->error(\sprintf('The file has %d rejected rows.', $summary->rejectedRows));

            return Command::FAILURE;
        }
        if (0 === $summary->stopRows) {
            $io->error('The file has no stop rows.');

            return Command::FAILURE;
        }

        $io->success('The file agrees with the mapping.');

        return Command::SUCCESS;
    }

    /**
     * Gives the values of a stop row in the order of FieldName::cases(). The times are in
     * APP_TIMEZONE, so that they agree with the cells of the file.
     *
     * @param bool $hideRowKey true if the row key has the person id
     *
     * @return list<string|int|float|null>
     */
    private function previewRow(StopRowDto $row, bool $hideRowKey): array
    {
        $timeZone = new \DateTimeZone($this->timeZone);

        return [
            $row->rowNumber,
            $hideRowKey ? self::HIDDEN : $row->rowKey,
            $row->route,
            null === $row->personId ? null : self::HIDDEN,
            $row->serviceDate->format('Y-m-d'),
            $row->run,
            $row->stopType->value,
            $row->stopStatus->value,
            $row->passengersAfter,
            $row->nextDistanceKm,
            $row->nextDurationMin,
            $row->plannedTime->setTimezone($timeZone)->format('Y-m-d H:i:s'),
            $row->actualTime?->setTimezone($timeZone)->format('Y-m-d H:i:s'),
            match ($row->plannedSolo) {
                true => 'yes',
                false => 'no',
                null => null,
            },
        ];
    }
}

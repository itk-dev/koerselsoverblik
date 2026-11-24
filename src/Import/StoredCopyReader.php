<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportBatch;
use App\Import\Extract\ExtractSummaryDto;
use App\Import\Extract\MissingColumnsException;
use App\Import\Extract\RejectedRowDto;
use App\Import\Extract\RejectionReason;
use App\Import\Extract\StopRowDto;
use App\Import\Extract\StopRowParser;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;
use App\Report\DateRangeDto;
use App\Repository\RejectedStopRowRepository;
use App\Repository\StopRowRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the stored copies of the rows of a range again through a mapping (ADR 006). After
 * go-live the upload files are deleted, so the copies are the only source of the dates that were
 * imported before. See "Read the stored copies again" in docs/architecture.md.
 *
 * The read works through the range one date at a time, as an import of one day. The copies of a
 * day fit in the memory, and a range of any length does not.
 */
final readonly class StoredCopyReader
{
    /**
     * The file name of the batch of a read. The list of the uploaded dates shows it.
     */
    public const string FILE_NAME = 'gemte kopier';

    public function __construct(
        private StopRowParser $parser,
        private StopRowReplacer $replacer,
        private RouteBuilder $routeBuilder,
        private RouteDayTotalsBuilder $totalsBuilder,
        private LocationDayTotalsBuilder $locationTotalsBuilder,
        private MonthCitizensBuilder $monthCitizens,
        private ClockInterface $clock,
        private StopRowRepository $stopRows,
        private RejectedStopRowRepository $rejectedRows,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.driving_data_audit')]
        private LoggerInterface $auditLogger,
    ) {
    }

    /**
     * Reads the copies of the range through the mapping, and counts. It changes no data.
     */
    public function preview(DateRangeDto $range, MappingDto $mapping): StoredCopyPreviewDto
    {
        $stopRows = $this->stopRows->countByDate($range);
        $rejectedRows = $this->rejectedRows->countByDate($range);
        $dates = self::dates($stopRows, $rejectedRows);
        [$required, $optional] = $this->missingColumns($dates, $mapping);

        return new StoredCopyPreviewDto(
            $dates,
            array_sum($stopRows),
            array_sum($rejectedRows),
            ExtractSummaryDto::fromRows($this->rowsOfDates($dates, $mapping), 0),
            $required,
            $optional,
        );
    }

    /**
     * Replaces the rows of each date of the range that has stored rows with the rows that the
     * copies give through the mapping, and builds the route runs, the totals and the citizens of
     * the date again. Each date is one transaction. A date with no stored rows is not changed.
     *
     * @param ?string $readBy the user identifier, or null for the console
     *
     * @throws MissingColumnsException if no copy of the range has a column of a required field or of the row id. No data is changed
     * @throws StoredCopyException     if the copies of a date give no stop rows, or a row with another date. That date and the dates after it keep their rows
     * @throws RowIdConflictException  if a row id is already stored on another date. That date and the dates after it keep their rows
     */
    public function read(DateRangeDto $range, MappingDto $mapping, ?string $readBy = null): StoredCopyResultDto
    {
        $dates = self::dates($this->stopRows->countByDate($range), $this->rejectedRows->countByDate($range));
        [$required] = $this->missingColumns($dates, $mapping);
        if ([] !== $required) {
            throw new MissingColumnsException($required);
        }

        $stopRows = 0;
        $rejectedRows = 0;
        $deletedRows = 0;
        foreach ($dates as $date) {
            $batch = $this->readDate(new \DateTimeImmutable($date), $mapping);
            $stopRows += $batch->getRowsInserted();
            $rejectedRows += $batch->getRowsRejected();
            $deletedRows += $batch->getRowsDeleted();
        }

        $user = $readBy ?? 'console';
        $firstDate = $range->firstDate->format('Y-m-d');
        $lastDate = $range->lastDate->format('Y-m-d');
        // The log has no field values from the extract.
        $this->auditLogger->info(
            \sprintf('%s read the stored copies from %s to %s again through the mapping "%s": %d dates, %d stop rows and %d rejected rows', $user, $firstDate, $lastDate, $mapping->name, \count($dates), $stopRows, $rejectedRows),
            ['user' => $user, 'first_date' => $firstDate, 'last_date' => $lastDate, 'mapping' => $mapping->name, 'dates' => \count($dates), 'stop_rows' => $stopRows, 'rejected_rows' => $rejectedRows],
        );

        return new StoredCopyResultDto(\count($dates), $stopRows, $rejectedRows, $deletedRows);
    }

    /**
     * Reads the copies of the date before the delete, because the replacement deletes them.
     */
    private function readDate(\DateTimeImmutable $date, MappingDto $mapping): ImportBatch
    {
        $day = ImportRangeDto::create($date, $date, 1);
        $copies = $this->copiesOn($date);

        try {
            return $this->entityManager->wrapInTransaction(function () use ($day, $mapping, $copies): ImportBatch {
                $batch = new ImportBatch($mapping->name, self::FILE_NAME, null, $this->clock->now(), $day);
                // The first flush of the replacer inserts the batch before the rows that refer to it.
                $this->entityManager->persist($batch);

                $result = $this->replacer->replace($day, $mapping, $this->rowsOnTheDate($day->firstDate, $this->parser->parseCopies(self::byRowNumber($copies), $mapping, $day)), $batch);
                // The exception rolls back the delete of the date.
                if (0 === $result->insertedRows) {
                    throw StoredCopyException::noStopRows($day->firstDate);
                }
                $batch->complete($result);
                $this->routeBuilder->rebuild($day);
                // The totals read the route runs, so they come after them.
                $this->totalsBuilder->rebuild($day);
                $this->locationTotalsBuilder->rebuild($day);
                $this->monthCitizens->rebuild(DateRangeDto::create($day->firstDate, $day->lastDate));

                return $batch;
            });
        } catch (UniqueConstraintViolationException) {
            // As in an import, the index of the row keys is the only unique index that can break.
            throw new RowIdConflictException();
        }
    }

    /**
     * Stops at a row with another date, so that the transaction rolls back and keeps the copy.
     *
     * @param iterable<StopRowDto|RejectedRowDto> $rows
     *
     * @return \Generator<StopRowDto|RejectedRowDto>
     */
    private function rowsOnTheDate(\DateTimeImmutable $date, iterable $rows): \Generator
    {
        foreach ($rows as $row) {
            if ($row instanceof RejectedRowDto && RejectionReason::OutsideRange === $row->reason) {
                throw StoredCopyException::otherDate($date, $row->rowNumber);
            }
            yield $row;
        }
    }

    /**
     * Gives the rows that the copies of the dates give, one date at a time, for the preview.
     *
     * @param list<string> $dates as Y-m-d
     *
     * @return \Generator<StopRowDto|RejectedRowDto>
     */
    private function rowsOfDates(array $dates, MappingDto $mapping): \Generator
    {
        foreach ($dates as $date) {
            $day = ImportRangeDto::create(new \DateTimeImmutable($date), new \DateTimeImmutable($date), 1);
            yield from $this->parser->parseCopies(self::byRowNumber($this->copiesOn($day->firstDate)), $mapping, $day);
        }
    }

    /**
     * Gives the copies of the stop rows and the rejected rows of the date. A rejected row keeps
     * its row number of the file. The stop rows have no row number, so they get their place.
     * Two copies can have the same number, so the copies are pairs, not keys.
     *
     * @return list<array{int, array<array-key, mixed>}> the row number and the copy
     */
    private function copiesOn(\DateTimeImmutable $date): array
    {
        $copies = [];
        foreach ($this->stopRows->copiesOn($date) as $place => $copy) {
            $copies[] = [$place + 1, $copy];
        }
        foreach ($this->rejectedRows->copiesOn($date) as $rejected) {
            $copies[] = [$rejected['rowNumber'], $rejected['values']];
        }

        return $copies;
    }

    /**
     * @param list<array{int, array<array-key, mixed>}> $copies
     *
     * @return \Generator<int, array<array-key, mixed>> keyed by the row number
     */
    private static function byRowNumber(array $copies): \Generator
    {
        foreach ($copies as [$rowNumber, $copy]) {
            yield $rowNumber => $copy;
        }
    }

    /**
     * Finds the field columns and the row id columns that no copy of the dates has.
     *
     * @param list<string> $dates as Y-m-d
     *
     * @return array{list<string>, list<string>} the columns of the required fields and the row id, and the columns of the optional fields
     */
    private function missingColumns(array $dates, MappingDto $mapping): array
    {
        // A range with no copies reads nothing, so no column is missing.
        if ([] === $dates) {
            return [[], []];
        }
        $columns = [];
        foreach ($dates as $date) {
            foreach ($this->copiesOn(new \DateTimeImmutable($date)) as [, $copy]) {
                $columns += array_flip(array_map(strval(...), array_keys($copy)));
            }
        }

        $required = array_values(array_filter($mapping->rowId, static fn (string $column): bool => !isset($columns[$column])));
        $optional = [];
        foreach (FieldName::cases() as $name) {
            $field = $mapping->field($name);
            if (isset($columns[$field->column])) {
                continue;
            }
            if ($field->required) {
                $required[] = $field->column;
            } else {
                $optional[] = $field->column;
            }
        }
        $required = array_values(array_unique($required));

        // A column of a required field and an optional field is only named as required.
        return [$required, array_values(array_diff(array_unique($optional), $required))];
    }

    /**
     * @param array<string, int> $stopRows     the count of each date, keyed by the date as Y-m-d
     * @param array<string, int> $rejectedRows the count of each date, keyed by the date as Y-m-d
     *
     * @return list<string> the dates with rows, sorted
     */
    private static function dates(array $stopRows, array $rejectedRows): array
    {
        $dates = array_map(strval(...), array_keys($stopRows + $rejectedRows));
        sort($dates);

        return $dates;
    }
}

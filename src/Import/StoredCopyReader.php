<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportBatch;
use App\Import\Extract\ExtractSummaryDto;
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

    /**
     * The user of a read in the log. Only the console reads the copies.
     */
    private const string USER = 'console';

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
     *
     * @throws StoredCopyException if a copy has a value of an unknown type
     */
    public function preview(DateRangeDto $range, MappingDto $mapping): StoredCopyPreviewDto
    {
        $stopRows = $this->stopRows->countByDate($range);
        $dates = self::dates($stopRows, $this->rejectedRows->countByDate($range));

        // The parse of the summary fills these, so the preview reads each copy one time.
        $columns = [];
        $storedRejectedRows = 0;
        $rowsWithAnotherDate = [];
        $datesWithNoStopRows = [];
        $rows = function () use ($dates, $mapping, $stopRows, &$columns, &$storedRejectedRows, &$rowsWithAnotherDate, &$datesWithNoStopRows): \Generator {
            foreach ($dates as $date) {
                $day = self::day($date);
                [$copies, $rejectedCopies] = $this->copiesOf($day);
                $storedRejectedRows += $rejectedCopies;
                foreach ($copies as [, $copy]) {
                    $columns += array_flip(array_map(strval(...), array_keys($copy)));
                }
                $found = 0;
                foreach ($this->parse($copies, $mapping, $day) as $row) {
                    if ($row instanceof StopRowDto) {
                        ++$found;
                    } elseif (RejectionReason::OutsideRange === $row->reason) {
                        $rowsWithAnotherDate[] = [$date, $row->rowNumber];
                    }
                    yield $row;
                }
                if (0 === $found && ($stopRows[$date] ?? 0) > 0) {
                    $datesWithNoStopRows[] = $date;
                }
            }
        };
        $summary = ExtractSummaryDto::fromRows($rows(), 0);
        [$required, $optional] = [] === $dates ? [[], []] : self::missingColumns($columns, $mapping);

        return new StoredCopyPreviewDto($dates, array_sum($stopRows), $storedRejectedRows, $summary, $required, $optional, $rowsWithAnotherDate, $datesWithNoStopRows);
    }

    /**
     * Replaces the rows of each date of the range that has stored rows with the rows that the
     * copies give through the mapping, and builds the route runs, the totals and the citizens of
     * the date again. Each date is one transaction. A date with no stored rows is not changed.
     *
     * The read makes the preview first, and does not start if the preview has a problem. The log
     * has the dates that are read, also when a date fails.
     *
     * @throws StoredCopyException    if the preview has a problem, or a copy has changed after the preview. A failed date and the dates after it keep their rows
     * @throws RowIdConflictException if a row id is already stored on another date. That date and the dates after it keep their rows
     *
     * After an exception during a date, Doctrine has closed the entity manager.
     */
    public function read(DateRangeDto $range, MappingDto $mapping): StoredCopyResultDto
    {
        $preview = $this->preview($range, $mapping);
        $problem = $preview->problem();
        if (null !== $problem) {
            throw $problem;
        }
        $stopRows = $this->stopRows->countByDate($range);

        $read = 0;
        $insertedRows = 0;
        $rejectedRows = 0;
        $deletedRows = 0;
        try {
            foreach ($preview->dates as $date) {
                $batch = $this->readDate(self::day($date), $mapping, ($stopRows[$date] ?? 0) > 0);
                ++$read;
                $insertedRows += $batch->getRowsInserted();
                $rejectedRows += $batch->getRowsRejected();
                $deletedRows += $batch->getRowsDeleted();
            }
        } finally {
            $this->log($range, $mapping, $read, $insertedRows, $rejectedRows, $preview->dates[$read] ?? null);
        }

        return new StoredCopyResultDto($read, $insertedRows, $rejectedRows, $deletedRows);
    }

    /**
     * Reads the copies of the date before the delete, because the replacement deletes them.
     *
     * @param bool $hadStopRows true if the date has stop rows. A date with only rejected rows can give no stop rows
     */
    private function readDate(ImportRangeDto $day, MappingDto $mapping, bool $hadStopRows): ImportBatch
    {
        [$copies] = $this->copiesOf($day);
        $date = $day->firstDate->format('Y-m-d');

        try {
            return $this->entityManager->wrapInTransaction(function () use ($day, $date, $mapping, $copies, $hadStopRows): ImportBatch {
                $batch = new ImportBatch($mapping->name, self::FILE_NAME, null, $this->clock->now(), $day);
                // The first flush of the replacer inserts the batch before the rows that refer to it.
                $this->entityManager->persist($batch);

                $result = $this->replacer->replace($day, $mapping, self::onTheDate($date, $this->parse($copies, $mapping, $day)), $batch);
                // The exception rolls back the delete of the date.
                if ($hadStopRows && 0 === $result->insertedRows) {
                    throw StoredCopyException::noStopRows($date);
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
     * The preview finds such a row first, so this only stops a copy that changed after the
     * preview.
     *
     * @param iterable<StopRowDto|RejectedRowDto> $rows
     *
     * @return \Generator<StopRowDto|RejectedRowDto>
     */
    private static function onTheDate(string $date, iterable $rows): \Generator
    {
        foreach ($rows as $row) {
            if ($row instanceof RejectedRowDto && RejectionReason::OutsideRange === $row->reason) {
                throw StoredCopyException::otherDate($date, $row->rowNumber);
            }
            yield $row;
        }
    }

    /**
     * Parses the copies of the date. A copy with a value of an unknown type gives an exception
     * with no value.
     *
     * @param list<array{int, array<array-key, mixed>}> $copies
     *
     * @return \Generator<StopRowDto|RejectedRowDto>
     */
    private function parse(array $copies, MappingDto $mapping, ImportRangeDto $day): \Generator
    {
        try {
            yield from $this->parser->parseCopies(self::byRowNumber($copies), $mapping, $day);
        } catch (\InvalidArgumentException $exception) {
            throw StoredCopyException::unreadableCopy($day->firstDate->format('Y-m-d'), $exception);
        }
    }

    /**
     * Gives the copies of the stop rows of the date, and of the rejected rows that the replacement
     * of the date deletes, with their row numbers. A stop row that was imported before the number
     * was stored gets its place on the date. Two copies can have the same number, so the copies
     * are pairs, not keys.
     *
     * @return array{list<array{int, array<array-key, mixed>}>, int} the row numbers and the copies, and the number of rejected rows of them
     */
    private function copiesOf(ImportRangeDto $day): array
    {
        $copies = [];
        foreach ($this->stopRows->copiesOn($day->firstDate) as $place => $copy) {
            $copies[] = [$copy['rowNumber'] ?? $place + 1, $copy['values']];
        }
        $rejected = $this->rejectedRows->copiesInRange($day);
        foreach ($rejected as $copy) {
            $copies[] = [$copy['rowNumber'], $copy['values']];
        }

        return [$copies, \count($rejected)];
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
     * Finds the field columns and the row id columns that no copy has.
     *
     * @param array<array-key, int> $columns the columns of the copies as keys
     *
     * @return array{list<string>, list<string>} the columns of the required fields and the row id, and the columns of the optional fields
     */
    private static function missingColumns(array $columns, MappingDto $mapping): array
    {
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
     * The log has the range, the mapping and the counts of the dates that are read, and the date
     * that failed. It has no field values from the extract.
     */
    private function log(DateRangeDto $range, MappingDto $mapping, int $dates, int $stopRows, int $rejectedRows, ?string $failedDate): void
    {
        $firstDate = $range->firstDate->format('Y-m-d');
        $lastDate = $range->lastDate->format('Y-m-d');
        $message = \sprintf('%s read the stored copies from %s to %s again through the mapping "%s": %d dates, %d stop rows and %d rejected rows', self::USER, $firstDate, $lastDate, $mapping->name, $dates, $stopRows, $rejectedRows);
        $context = ['user' => self::USER, 'first_date' => $firstDate, 'last_date' => $lastDate, 'mapping' => $mapping->name, 'dates' => $dates, 'stop_rows' => $stopRows, 'rejected_rows' => $rejectedRows];
        if (null === $failedDate) {
            $this->auditLogger->info($message, $context);

            return;
        }
        $this->auditLogger->warning(\sprintf('%s. The read stopped at %s', $message, $failedDate), [...$context, 'failed_date' => $failedDate]);
    }

    private static function day(string $date): ImportRangeDto
    {
        $day = new \DateTimeImmutable($date);

        return ImportRangeDto::create($day, $day, 1);
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

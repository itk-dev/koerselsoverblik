<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportBatch;
use App\Entity\User;
use App\Import\Extract\ExtractSummaryDto;
use App\Import\Extract\InvalidExtractException;
use App\Import\Extract\MissingColumnsException;
use App\Import\Extract\StopRowParser;
use App\Import\Mapping\MappingDto;
use App\Report\DateRangeDto;
use App\Repository\StopRowRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * The one service for the upload page and the import command. See "Pipeline" in
 * docs/architecture.md.
 */
final readonly class DrivingDataImporter
{
    public function __construct(
        private StopRowParser $parser,
        private StopRowReplacer $replacer,
        private RouteBuilder $routeBuilder,
        private RouteDayTotalsBuilder $totalsBuilder,
        private LocationDayTotalsBuilder $locationTotalsBuilder,
        private MonthCitizensBuilder $monthCitizens,
        private StopRowRepository $stopRows,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Gives the first to the last date with stop rows in the file. The bulk upload uses it in place
     * of a range that the user types. It changes no data.
     *
     * @param int $maxDays the value of APP_DRIVING_DATA_MAX_DAYS
     *
     * @throws MissingColumnsException     if the extract does not have all the columns of the mapping
     * @throws InvalidExtractException     if the extract is above a limit, or is not a valid extract
     * @throws NoStopRowsException         if the file has no stop rows
     * @throws InvalidImportRangeException if the dates span more than $maxDays days
     */
    public function rangeOf(string $path, MappingDto $mapping, int $maxDays): ImportRangeDto
    {
        // ponytail: the preview parses the file again. Return the summary too if large files are slow.
        $dates = ExtractSummaryDto::fromRows($this->parser->parse($path, $mapping, null), 0)->dates;
        if ([] === $dates) {
            throw new NoStopRowsException();
        }

        return ImportRangeDto::create(new \DateTimeImmutable($dates[0]), new \DateTimeImmutable($dates[\count($dates) - 1]), $maxDays);
    }

    /**
     * Parses the file and counts. It changes no data.
     *
     * @param int $previewSize the number of stop rows in the preview
     *
     * @throws MissingColumnsException if the extract does not have all the columns of the mapping
     * @throws InvalidExtractException if the extract is above a limit, or is not a valid extract
     */
    public function preview(string $path, ImportRangeDto $range, MappingDto $mapping, int $previewSize): ImportPreviewDto
    {
        $summary = ExtractSummaryDto::fromRows($this->parser->parse($path, $mapping, $range), $previewSize);

        return new ImportPreviewDto(
            $summary,
            $this->stopRows->countInRange($range),
            array_values(array_diff($range->dates(), $summary->dates)),
        );
    }

    /**
     * Replaces the stop rows of the range with the rows of the file, builds the route runs, the
     * totals of each route and day, and the citizens of the months of the range again, and saves
     * the batch. All in one transaction, so a failed import leaves the data as it was. After an
     * exception, Doctrine has closed the entity manager.
     *
     * @param ?User  $user     the user of the upload page, or null for the console
     * @param string $fileName the name of the uploaded file, not the path of the temporary file
     *
     * @throws MissingColumnsException if the extract does not have all the columns of the mapping
     * @throws InvalidExtractException if the extract is above a limit, or is not a valid extract
     * @throws RowIdConflictException  if a row id of the file is already stored on a date outside the range
     * @throws NoStopRowsException     if the file has no stop rows in the range
     */
    public function import(string $path, ImportRangeDto $range, MappingDto $mapping, ?User $user, string $fileName): ImportBatch
    {
        try {
            return $this->replaceInTransaction($path, $range, $mapping, $user, $fileName);
        } catch (UniqueConstraintViolationException) {
            // The index of the row keys is the only unique index that the import can break. The
            // replacer creates one route and one sub-agreement for each name.
            throw new RowIdConflictException();
        }
    }

    private function replaceInTransaction(string $path, ImportRangeDto $range, MappingDto $mapping, ?User $user, string $fileName): ImportBatch
    {
        return $this->entityManager->wrapInTransaction(function () use ($path, $range, $mapping, $user, $fileName): ImportBatch {
            $batch = new ImportBatch($mapping->name, $fileName, $user, $this->clock->now(), $range);
            // The first flush of the replacer inserts the batch before the rows that refer to it.
            $this->entityManager->persist($batch);

            $result = $this->replacer->replace($range, $mapping, $this->parser->parse($path, $mapping, $range), $batch);
            // The exception rolls back the delete of the range.
            if (0 === $result->insertedRows) {
                throw new NoStopRowsException();
            }
            // wrapInTransaction() flushes the completed batch before the commit.
            $batch->complete($result);
            $this->routeBuilder->rebuild($range);
            // The totals read the route runs, so they come after them.
            $this->totalsBuilder->rebuild($range);
            $this->locationTotalsBuilder->rebuild($range);
            $this->monthCitizens->rebuild(DateRangeDto::create($range->firstDate, $range->lastDate));

            return $batch;
        });
    }
}

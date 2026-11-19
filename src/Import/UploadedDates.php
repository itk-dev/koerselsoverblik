<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportBatch;
use App\Report\MonthDto;
use App\Repository\ImportBatchRepository;
use App\Repository\RejectedStopRowRepository;
use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;

/**
 * Gives the dates with driving data of a month, for the upload page.
 */
final readonly class UploadedDates
{
    public function __construct(
        private StopRowRepository $stopRows,
        private RouteRunRepository $routeRuns,
        private RejectedStopRowRepository $rejectedRows,
        private ImportBatchRepository $batches,
    ) {
    }

    /**
     * Gives the dates of the month with stop rows or rejected rows, in the order of the date. A
     * date with only rejected rows is in the list, so that the page can clear its rows. Four
     * queries give all the dates.
     *
     * @return list<UploadedDateDto>
     */
    public function forMonth(MonthDto $month): array
    {
        $range = $month->range();
        $stopRows = $this->stopRows->countByDate($range);
        $routeRuns = $this->routeRuns->countByDate($range);
        $rejectedRows = $this->rejectedRows->countByDate($range);
        $batches = $this->batches->findOverlapping($range);
        $keys = array_keys($stopRows + $rejectedRows);
        sort($keys);
        $dates = [];
        foreach ($keys as $date) {
            $dates[] = new UploadedDateDto(
                new \DateTimeImmutable($date),
                $stopRows[$date] ?? 0,
                $routeRuns[$date] ?? 0,
                $rejectedRows[$date] ?? 0,
                self::lastUpload($batches, $date),
            );
        }

        return $dates;
    }

    /**
     * An import replaces all dates of its range, so the newest batch whose range has the date
     * stored the rows of the date.
     *
     * @param list<ImportBatch> $batches the batches, newest first
     * @param string            $date    the date as Y-m-d
     */
    private static function lastUpload(array $batches, string $date): ?ImportBatch
    {
        foreach ($batches as $batch) {
            if ($batch->getFirstDate()->format('Y-m-d') <= $date && $date <= $batch->getLastDate()->format('Y-m-d')) {
                return $batch;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Import;

use App\Report\DateRangeDto;
use App\Repository\LocationDayTotalsRepository;
use App\Repository\RejectedStopRowRepository;
use App\Repository\RouteDayTotalsRepository;
use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The one service for the clear of the driving data on the upload page and with the clear command.
 * See "Clearing driving data" in docs/architecture.md.
 */
final readonly class DrivingDataClearer
{
    public function __construct(
        private StopRowRangeDeleter $deleter,
        private StopRowRepository $stopRows,
        private RouteRunRepository $routeRuns,
        private RejectedStopRowRepository $rejectedRows,
        private RouteDayTotalsRepository $routeTotals,
        private LocationDayTotalsRepository $locationTotals,
        private MonthCitizensBuilder $monthCitizens,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.driving_data_audit')]
        private LoggerInterface $auditLogger,
    ) {
    }

    /**
     * Counts the rows that a clear of the range deletes. It changes no data.
     */
    public function count(ImportRangeDto $range): ClearedRowsDto
    {
        return new ClearedRowsDto(
            $this->stopRows->countInRange($range),
            $this->routeRuns->countInRange($range),
            $this->rejectedRows->countInRange($range),
        );
    }

    /**
     * Deletes the stop rows, the rejected rows, their copies, the route runs and the totals of
     * each route and day of the range, and builds the citizens of the months of the range again.
     * All in one transaction, so a failed clear leaves the data as it was. The batches stay.
     * After an exception, Doctrine has closed the entity manager.
     *
     * @param ?string $clearedBy the user identifier of the upload page, or null for the console
     */
    public function clear(ImportRangeDto $range, ?string $clearedBy): ClearedRowsDto
    {
        $cleared = $this->entityManager->wrapInTransaction(function () use ($range): ClearedRowsDto {
            // The counts come from the deletes, so they are exact also when another change runs.
            $deleted = $this->deleter->deleteRange($range);
            $routeRuns = $this->routeRuns->deleteInRange($range);
            $this->routeTotals->deleteInRange($range);
            $this->locationTotals->deleteInRange($range);
            $this->monthCitizens->rebuild(DateRangeDto::create($range->firstDate, $range->lastDate));

            return new ClearedRowsDto($deleted->stopRows, $routeRuns, $deleted->rejectedRows);
        });

        $user = $clearedBy ?? 'console';
        $firstDate = $range->firstDate->format('Y-m-d');
        $lastDate = $range->lastDate->format('Y-m-d');
        // The log has no field values from the extract.
        $this->auditLogger->info(
            \sprintf('%s cleared the driving data from %s to %s: %d stop rows, %d route runs and %d rejected rows', $user, $firstDate, $lastDate, $cleared->stopRows, $cleared->routeRuns, $cleared->rejectedRows),
            ['user' => $user, 'first_date' => $firstDate, 'last_date' => $lastDate, 'stop_rows' => $cleared->stopRows, 'route_runs' => $cleared->routeRuns, 'rejected_rows' => $cleared->rejectedRows],
        );

        return $cleared;
    }
}

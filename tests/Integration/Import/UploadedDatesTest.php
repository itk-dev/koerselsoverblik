<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\Entity\ImportBatch;
use App\Entity\RejectedStopRow;
use App\Entity\Route;
use App\Entity\RouteRun;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Entity\User;
use App\Import\Extract\RejectedRowDto;
use App\Import\Extract\RejectionReason;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Import\RouteRunDto;
use App\Import\UploadedDateDto;
use App\Import\UploadedDates;
use App\Report\MonthDto;
use App\Repository\ImportBatchRepository;
use App\Repository\RejectedStopRowRepository;
use App\Repository\RouteRunRepository;
use App\Repository\StopRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the dates of a month on the upload page: the dates with stop rows, their counts, and the
 * last upload of each date.
 */
final class UploadedDatesTest extends KernelTestCase
{
    /**
     * The last upload of a date is the newest batch whose range has the date. A date with stop rows
     * and no route runs has 0 runs. A date with only rejected rows is in the result, so that the
     * page can clear it. A date outside the month is not in the result.
     */
    public function testForMonthGivesTheDatesWithDrivingDataAndTheirLastUpload(): void
    {
        $this->store();

        $dates = $this->uploadedDates()->forMonth(MonthDto::fromQuery('2026-01', new \DateTimeImmutable('2026-02-10')));

        self::assertSame(
            [
                ['2026-01-05', 3, 1, 1, 1, 'older.xlsx'],
                ['2026-01-06', 1, 0, 1, 0, 'newer.xlsx'],
                ['2026-01-07', 1, 0, 0, 0, 'newer.xlsx'],
                ['2026-01-08', 0, 0, 0, 2, 'newer.xlsx'],
                ['2026-01-20', 1, 0, 0, 0, null],
            ],
            array_map(static fn (UploadedDateDto $date): array => [$date->date->format('Y-m-d'), $date->stopRows, $date->ignoredStopRows, $date->routeRuns, $date->rejectedRows, $date->lastUpload?->getFileName()], $dates),
        );
        self::assertSame('admin@example.com', $dates[0]->lastUpload?->getUploadedBy()?->getUserIdentifier());
        self::assertNull($dates[1]->lastUpload?->getUploadedBy());
    }

    public function testForMonthWithNoStopRowsIsEmpty(): void
    {
        $this->store();

        self::assertSame([], $this->uploadedDates()->forMonth(MonthDto::fromQuery('2025-12', new \DateTimeImmutable('2026-02-10'))));
    }

    /**
     * Stores two batches that overlap on 6 January, stop rows on 5, 6, 7 and 20 January and on 1
     * February, route runs on 5 and 6 January, and rejected rows on 5 and 8 January and with no
     * date. One stop row on 5 January is of an ignored sub-agreement. No batch has 20 January in
     * its range.
     */
    private function store(): void
    {
        $entityManager = $this->entityManager();
        $user = new User('admin@example.com');
        $older = new ImportBatch('default', 'older.xlsx', $user, new \DateTimeImmutable('2026-01-08 10:00'), self::range('2026-01-05', '2026-01-06'));
        $newer = new ImportBatch('default', 'newer.xlsx', null, new \DateTimeImmutable('2026-01-08 11:00'), self::range('2026-01-06', '2026-01-08'));
        $later = new ImportBatch('default', 'later.xlsx', null, new \DateTimeImmutable('2026-02-02 11:00'), self::range('2026-02-01', '2026-02-01'));
        $subAgreement = new SubAgreement()->setReference('R');
        $route = new Route('R-01', $subAgreement);
        $ignoredSubAgreement = new SubAgreement()->setReference('I')->setIgnored(true);
        $ignoredRoute = new Route('I-01', $ignoredSubAgreement);
        $entities = [$user, $older, $newer, $later, $subAgreement, $route, $ignoredSubAgreement, $ignoredRoute];
        foreach ([['2026-01-05', $older], ['2026-01-05', $older], ['2026-01-06', $newer], ['2026-01-07', $newer], ['2026-01-20', $older], ['2026-02-01', $later]] as $index => [$date, $batch]) {
            $entities[] = new StopRow(self::stopRowDto($date, $index), 'default', $route, $batch);
        }
        // The figures do not count the routes of an ignored sub-agreement, but the rows are stored.
        $entities[] = new StopRow(self::stopRowDto('2026-01-05', 99), 'default', $ignoredRoute, $older);
        foreach (['2026-01-05', '2026-01-06'] as $date) {
            $entities[] = new RouteRun($route, new \DateTimeImmutable($date), 1, new RouteRunDto(null, null, null, null, null, null, false, null));
        }
        foreach ([['2026-01-05', $older], ['2026-01-08', $newer], ['2026-01-08', $newer], [null, $newer]] as [$date, $batch]) {
            $entities[] = new RejectedStopRow(new RejectedRowDto(5, RejectionReason::InvalidRowId, null, null === $date ? null : new \DateTimeImmutable($date), []), $batch);
        }
        foreach ($entities as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $entityManager->clear();
    }

    private static function stopRowDto(string $date, int $index): StopRowDto
    {
        $serviceDate = new \DateTimeImmutable($date);

        return new StopRowDto(
            rowNumber: 4,
            rowKey: 'S-'.$index,
            route: 'R-01',
            personId: null,
            serviceDate: $serviceDate,
            run: 1,
            stopType: StopType::Pickup,
            stopStatus: StopStatus::Completed,
            passengersAfter: null,
            nextDistanceKm: null,
            nextDurationMin: null,
            plannedTime: $serviceDate->setTime(7, 10),
            actualTime: null,
            plannedSolo: null,
            locationName: null,
            sourceValues: ['RouteName' => 'R-01'],
        );
    }

    private static function range(string $firstDate, string $lastDate): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate), 31);
    }

    private function uploadedDates(): UploadedDates
    {
        $stopRows = self::getContainer()->get(StopRowRepository::class);
        self::assertInstanceOf(StopRowRepository::class, $stopRows);
        $routeRuns = self::getContainer()->get(RouteRunRepository::class);
        self::assertInstanceOf(RouteRunRepository::class, $routeRuns);
        $rejectedRows = self::getContainer()->get(RejectedStopRowRepository::class);
        self::assertInstanceOf(RejectedStopRowRepository::class, $rejectedRows);
        $batches = self::getContainer()->get(ImportBatchRepository::class);
        self::assertInstanceOf(ImportBatchRepository::class, $batches);

        return new UploadedDates($stopRows, $routeRuns, $rejectedRows, $batches);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\Entity\ImportBatch;
use App\Entity\LocationDayTotals;
use App\Entity\Route;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\LocationDayTotalsBuilder;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Report\PunctualityCountDto;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks that the builder stores the measured stops of each location, route and day of a range.
 * The test environment has a tolerance of 5 minutes.
 */
final class LocationDayTotalsBuilderTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private ?ImportBatch $batch = null;

    private int $rowKey = 0;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    /**
     * A measured stop is a completed pickup or drop-off with a delay. A cancelled stop, a stop with
     * no registration, a stop with no actual time and a stop with no location are not counted. A
     * delay of 5 minutes is on time.
     */
    public function testEachLocationRouteAndDateHasOneRow(): void
    {
        $first = $this->route('A-01');
        $second = $this->route('B-01');
        $this->stop($first, '2026-01-05', StopType::Pickup, StopStatus::Completed, 300, 'Skole 1');
        $this->stop($first, '2026-01-05', StopType::DropOff, StopStatus::Completed, 301, 'Skole 1');
        $this->stop($first, '2026-01-05', StopType::DropOff, StopStatus::Completed, 0, 'Skole 2');
        $this->stop($first, '2026-01-05', StopType::DropOff, StopStatus::Cancelled, 0, 'Skole 1');
        $this->stop($first, '2026-01-05', StopType::DropOff, StopStatus::Pending, 0, 'Skole 1');
        $this->stop($first, '2026-01-05', StopType::DropOff, StopStatus::Completed, null, 'Skole 1');
        $this->stop($first, '2026-01-05', StopType::DropOff, StopStatus::Completed, 0, null);
        $this->stop($second, '2026-01-06', StopType::DropOff, StopStatus::Completed, 0, 'Skole 1');

        self::assertSame(3, $this->builder()->rebuild(self::range('2026-01-05', '2026-01-06')));

        self::assertEquals([
            'A-01 2026-01-05 Skole 1' => new PunctualityCountDto(1, 2),
            'A-01 2026-01-05 Skole 2' => new PunctualityCountDto(1, 1),
            'B-01 2026-01-06 Skole 1' => new PunctualityCountDto(1, 1),
        ], $this->rows());
    }

    /**
     * A rebuild deletes and builds the rows of its range only. A date with no measured stops at a
     * location gets no row.
     */
    public function testRebuildChangesOnlyItsRange(): void
    {
        $route = $this->route('A-01');
        $this->stop($route, '2026-01-05', StopType::DropOff, StopStatus::Completed, 0, 'Skole 1');
        $this->entityManager->persist(new LocationDayTotals($route, new \DateTimeImmutable('2026-01-06'), 'Skole 1', 5, new PunctualityCountDto(1, 1)));
        $this->entityManager->flush();

        self::assertSame(1, $this->builder()->rebuild(self::range('2026-01-05', '2026-01-05')));
        self::assertCount(2, $this->rows());

        self::assertSame(0, $this->builder()->rebuild(self::range('2026-01-06', '2026-01-06')));
        self::assertSame(['A-01 2026-01-05 Skole 1'], array_keys($this->rows()));
    }

    /**
     * Each row has the tolerance of the build, so that a later change of the tolerance is found.
     */
    public function testRowHasTheToleranceOfTheBuild(): void
    {
        $this->stop($this->route('A-01'), '2026-01-05', StopType::DropOff, StopStatus::Completed, 0, 'Skole 1');

        $this->builder()->rebuild(self::range('2026-01-05', '2026-01-05'));

        $this->entityManager->clear();
        $row = $this->entityManager->getRepository(LocationDayTotals::class)->findOneBy([]);
        self::assertInstanceOf(LocationDayTotals::class, $row);
        self::assertSame(5, $row->getToleranceMinutes());
    }

    /**
     * The builder writes the rows in groups, so the memory use does not grow with the range.
     */
    public function testMoreRowsThanOneGroupAreAllStored(): void
    {
        $route = $this->route('A-01');
        for ($number = 1; $number <= 600; ++$number) {
            $this->stop($route, '2026-01-05', StopType::DropOff, StopStatus::Completed, 0, 'Skole '.$number);
        }
        $builder = $this->builder();
        $flushes = new FlushCounter();
        $this->entityManager->getEventManager()->addEventListener(Events::postFlush, $flushes);

        $built = $builder->rebuild(self::range('2026-01-05', '2026-01-05'));

        // One flush for the full group, and one for the rows after it.
        self::assertSame(2, $flushes->count);
        self::assertSame(600, $built);
        self::assertCount(600, $this->rows());
    }

    /**
     * @return array<string, PunctualityCountDto> keyed by the route, the date and the location
     */
    private function rows(): array
    {
        $this->entityManager->clear();
        $rows = [];
        foreach ($this->entityManager->getRepository(LocationDayTotals::class)->findAll() as $row) {
            $rows[\sprintf('%s %s %s', $row->getRoute()->getSourceKey(), $row->getServiceDate()->format('Y-m-d'), $row->getLocationName())] = $row->getStops();
        }
        ksort($rows);

        return $rows;
    }

    private function builder(): LocationDayTotalsBuilder
    {
        $this->entityManager->flush();
        $builder = self::getContainer()->get(LocationDayTotalsBuilder::class);
        self::assertInstanceOf(LocationDayTotalsBuilder::class, $builder);

        return $builder;
    }

    private static function range(string $firstDate, string $lastDate): ImportRangeDto
    {
        return ImportRangeDto::create(new \DateTimeImmutable($firstDate), new \DateTimeImmutable($lastDate), 31);
    }

    private function route(string $name): Route
    {
        $subAgreement = new SubAgreement()->setReference(substr($name, 0, 1));
        $this->entityManager->persist($subAgreement);
        $route = new Route($name, $subAgreement);
        $this->entityManager->persist($route);

        return $route;
    }

    /**
     * Stores one stop. The planned time is 07:00 on the date.
     *
     * @param ?int $delaySeconds the actual time minus the planned time, or null for no actual time
     */
    private function stop(Route $route, string $date, StopType $type, StopStatus $status, ?int $delaySeconds, ?string $location): void
    {
        $serviceDate = new \DateTimeImmutable($date);
        $planned = $serviceDate->setTime(7, 0);
        $row = new StopRowDto(
            rowNumber: ++$this->rowKey,
            rowKey: 'S-'.$this->rowKey,
            route: $route->getSourceKey(),
            personId: 'P-'.$this->rowKey,
            serviceDate: $serviceDate,
            run: 1,
            stopType: $type,
            stopStatus: $status,
            passengersAfter: null,
            nextDistanceKm: null,
            nextDurationMin: null,
            plannedTime: $planned,
            actualTime: null === $delaySeconds ? null : $planned->modify(\sprintf('%+d seconds', $delaySeconds)),
            plannedSolo: null,
            locationName: $location,
        );
        $this->entityManager->persist(new StopRow($row, 'default', $route, $this->batch()));
    }

    private function batch(): ImportBatch
    {
        if (null === $this->batch) {
            $this->batch = new ImportBatch('default', 'test.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-31'), 31));
            $this->entityManager->persist($this->batch);
        }

        return $this->batch;
    }
}

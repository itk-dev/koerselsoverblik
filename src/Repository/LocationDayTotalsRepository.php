<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LocationDayTotals;
use App\Import\ImportRangeDto;
use App\Report\DateRangeDto;
use App\Report\LocationPunctualityDto;
use App\Report\PunctualityCountDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds the measured stops of the locations. The query applies CountedRouteFilter, so the routes of
 * an ignored sub-agreement are left out, and the supplier filter applies. See "Data model" in
 * docs/architecture.md.
 *
 * @extends ServiceEntityRepository<LocationDayTotals>
 */
class LocationDayTotalsRepository extends ServiceEntityRepository
{
    /**
     * @param int $toleranceMinutes the value of APP_PUNCTUALITY_TOLERANCE_MINUTES
     */
    public function __construct(
        ManagerRegistry $registry,
        private readonly CountedRouteFilter $countedRoutes,
        #[Autowire(param: 'app.punctuality_tolerance_minutes')]
        private readonly int $toleranceMinutes,
    ) {
        parent::__construct($registry, LocationDayTotals::class);
    }

    /**
     * Gives the sum of each location in the range. A location with no rows has no item.
     *
     * @param ?int $supplierId the id of a supplier, or null for all suppliers
     *
     * @return list<LocationPunctualityDto> in no fixed order
     *
     * @throws OtherToleranceException if a row was built with another tolerance
     */
    public function sumOfEachLocation(DateRangeDto $range, ?int $supplierId = null): array
    {
        $builder = $this->createQueryBuilder('totals')
            ->select('totals.locationName AS location', 'SUM(totals.onTime) AS onTime', 'SUM(totals.measured) AS measured')
            ->addSelect('SUM(CASE WHEN totals.toleranceMinutes = :tolerance THEN 0 ELSE 1 END) AS otherTolerance')
            ->where('totals.serviceDate BETWEEN :first AND :last')
            ->groupBy('totals.locationName')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->setParameter('tolerance', $this->toleranceMinutes);

        /** @var list<array{location: string, onTime: int|string, measured: int|string, otherTolerance: int|string}> $rows */
        $rows = $this->countedRoutes->apply($builder, 'totals.route', $supplierId)->getQuery()->getArrayResult();

        return array_map(function (array $row): LocationPunctualityDto {
            if (0 !== (int) $row['otherTolerance']) {
                throw new OtherToleranceException($this->toleranceMinutes);
            }

            // MariaDB can give a sum as text.
            return new LocationPunctualityDto($row['location'], new PunctualityCountDto((int) $row['onTime'], (int) $row['measured']));
        }, $rows);
    }

    /**
     * Deletes the rows in the range.
     */
    public function deleteInRange(ImportRangeDto $range): void
    {
        $this->createQueryBuilder('totals')
            ->delete()
            ->where('totals.serviceDate BETWEEN :first AND :last')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->execute();
    }

    /**
     * Deletes all rows, before a build of all dates.
     */
    public function deleteAll(): void
    {
        $this->createQueryBuilder('totals')->delete()->getQuery()->execute();
    }
}

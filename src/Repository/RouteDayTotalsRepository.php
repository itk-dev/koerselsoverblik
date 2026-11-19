<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RouteDayTotals;
use App\Import\ImportRangeDto;
use App\Report\DateRangeDto;
use App\Report\DayTotalsDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds the counters of the routes and days that the figures count. Each query applies
 * CountedRouteFilter, so the routes of an ignored sub-agreement are left out, and the supplier
 * filter applies. See "Data model" in docs/architecture.md.
 *
 * @extends ServiceEntityRepository<RouteDayTotals>
 */
class RouteDayTotalsRepository extends ServiceEntityRepository
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
        parent::__construct($registry, RouteDayTotals::class);
    }

    /**
     * Gives the sum of the range.
     *
     * @param ?int $supplierId the id of a supplier, or null for all suppliers
     *
     * @throws OtherToleranceException if a row was built with another tolerance
     */
    public function sum(DateRangeDto $range, ?int $supplierId = null): DayTotalsDto
    {
        /** @var array<string, int|string|null> $row */
        $row = $this->sumQuery($range, $supplierId)->getQuery()->getSingleResult();
        $this->checkTolerance($row);

        return DayTotalsDto::fromRow($row);
    }

    /**
     * Gives the sum of each month in the range. A month with no rows has no item.
     *
     * @param ?int $supplierId the id of a supplier, or null for all suppliers
     *
     * @return array<string, DayTotalsDto> keyed by the month as YYYY-MM, as MonthDto::query()
     *
     * @throws OtherToleranceException if a row was built with another tolerance
     */
    public function sumByMonth(DateRangeDto $range, ?int $supplierId = null): array
    {
        /** @var list<array<string, int|string|null>> $rows */
        $rows = $this->sumQuery($range, $supplierId)
            ->addSelect(MonthKey::of('totals.serviceDate').' AS month')
            ->groupBy('month')
            ->getQuery()
            ->getArrayResult();

        $sums = [];
        foreach ($rows as $row) {
            $this->checkTolerance($row);
            $sums[(string) $row['month']] = DayTotalsDto::fromRow($row);
        }

        return $sums;
    }

    /**
     * Gives the sum of each route in the range, for all suppliers. A route with no rows has no
     * item.
     *
     * @return array<int, DayTotalsDto> keyed by the route id
     *
     * @throws OtherToleranceException if a row was built with another tolerance
     */
    public function sumOfEachRoute(DateRangeDto $range): array
    {
        /** @var list<array<string, int|string|null>> $rows */
        $rows = $this->sumQuery($range, null)
            ->addSelect('IDENTITY(totals.route) AS route')
            ->groupBy('totals.route')
            ->getQuery()
            ->getArrayResult();

        $sums = [];
        foreach ($rows as $row) {
            $this->checkTolerance($row);
            // MariaDB can give an id as text.
            $sums[(int) $row['route']] = DayTotalsDto::fromRow($row);
        }

        return $sums;
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

    /**
     * Makes the query of the sums. It also counts the rows with another tolerance, so that a
     * check needs no extra query.
     */
    private function sumQuery(DateRangeDto $range, ?int $supplierId): QueryBuilder
    {
        $builder = $this->createQueryBuilder('totals')
            ->select(array_map(static fn (string $field): string => \sprintf('SUM(totals.%1$s) AS %1$s', $field), DayTotalsDto::FIELDS))
            ->addSelect('SUM(CASE WHEN totals.toleranceMinutes = :tolerance THEN 0 ELSE 1 END) AS otherTolerance')
            ->where('totals.serviceDate BETWEEN :first AND :last')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->setParameter('tolerance', $this->toleranceMinutes);

        return $this->countedRoutes->apply($builder, 'totals.route', $supplierId);
    }

    /**
     * @param array<string, int|string|null> $row
     *
     * @throws OtherToleranceException
     */
    private function checkTolerance(array $row): void
    {
        if (0 !== (int) ($row['otherTolerance'] ?? 0)) {
            throw new OtherToleranceException($this->toleranceMinutes);
        }
    }
}

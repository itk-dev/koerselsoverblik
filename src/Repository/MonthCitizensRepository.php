<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MonthCitizens;
use App\Report\DateRangeDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Reads and deletes the citizens of each month. A month with no row has no citizens.
 *
 * @extends ServiceEntityRepository<MonthCitizens>
 */
class MonthCitizensRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonthCitizens::class);
    }

    /**
     * Gives the citizens of each month that starts in the range.
     *
     * @param ?int $supplierId the id of a supplier, or null for all suppliers
     *
     * @return array<string, int> keyed by the month as YYYY-MM, as MonthDto::query()
     */
    public function byMonth(DateRangeDto $range, ?int $supplierId = null): array
    {
        /** @var list<array{firstDate: \DateTimeImmutable, citizens: int}> $rows */
        $rows = $this->inScope($this->createQueryBuilder('months'), $supplierId)
            ->select('months.firstDate', 'months.citizens')
            ->andWhere('months.firstDate BETWEEN :first AND :last')
            ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
            ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getArrayResult();

        $citizens = [];
        foreach ($rows as $row) {
            $citizens[$row['firstDate']->format('Y-m')] = $row['citizens'];
        }

        return $citizens;
    }

    /**
     * Deletes the rows of the months that start in the range, or of all months if the range is
     * null.
     *
     * @param ?int $supplierId the id of a supplier, or null for all suppliers
     */
    public function deleteMonths(?DateRangeDto $range, ?int $supplierId): void
    {
        $builder = $this->inScope($this->createQueryBuilder('months')->delete(), $supplierId);
        if (null !== $range) {
            $builder
                ->andWhere('months.firstDate BETWEEN :first AND :last')
                ->setParameter('first', $range->firstDate, Types::DATE_IMMUTABLE)
                ->setParameter('last', $range->lastDate, Types::DATE_IMMUTABLE);
        }
        $builder->getQuery()->execute();
    }

    private function inScope(QueryBuilder $builder, ?int $supplierId): QueryBuilder
    {
        if (null === $supplierId) {
            return $builder->where('months.supplier IS NULL');
        }

        return $builder
            ->where('IDENTITY(months.supplier) = :supplier')
            ->setParameter('supplier', $supplierId);
    }
}

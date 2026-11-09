<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SubAgreement;
use App\Entity\SubAgreementSupplierAssignment;
use App\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubAgreementSupplierAssignment>
 */
class SubAgreementSupplierAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubAgreementSupplierAssignment::class);
    }

    /**
     * Gives the supplier of the sub-agreement on the date, or null if the date has no assignment.
     * The figures show such trips as "Unknown". If assignments overlap, the latest first date wins,
     * as in SubAgreement::getAssignmentOn().
     */
    public function findSupplier(SubAgreement $subAgreement, \DateTimeInterface $date): ?Supplier
    {
        $assignment = $this->createQueryBuilder('assignment')
            ->andWhere('assignment.subAgreement = :subAgreement')
            ->andWhere('assignment.validFrom <= :date')
            ->andWhere('assignment.validTo IS NULL OR assignment.validTo >= :date')
            ->setParameter('subAgreement', $subAgreement)
            ->setParameter('date', \DateTimeImmutable::createFromInterface($date), Types::DATE_IMMUTABLE)
            ->orderBy('assignment.validFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $assignment instanceof SubAgreementSupplierAssignment ? $assignment->getSupplier() : null;
    }
}

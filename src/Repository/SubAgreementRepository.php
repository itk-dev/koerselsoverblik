<?php

namespace App\Repository;

use App\Entity\SubAgreement;
use App\Entity\SubAgreementSupplierAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubAgreement>
 */
class SubAgreementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubAgreement::class);
    }

    /**
     * Gives all sub-agreements. The sub-agreements without an assignment on the date come first,
     * because an administrator must assign them. Each group is sorted by the reference.
     *
     * @return list<SubAgreement>
     */
    public function findAllWithoutCurrentAssignmentFirst(\DateTimeInterface $date): array
    {
        /** @var list<SubAgreement> $subAgreements */
        $subAgreements = $this->createQueryBuilder('subAgreement')
            ->leftJoin(
                SubAgreementSupplierAssignment::class,
                'current',
                'WITH',
                'current.subAgreement = subAgreement AND current.validFrom <= :date AND (current.validTo IS NULL OR current.validTo >= :date)',
            )
            ->addSelect('COUNT(current.id) AS HIDDEN currentAssignments')
            ->groupBy('subAgreement.id')
            ->orderBy('currentAssignments', 'ASC')
            ->addOrderBy('subAgreement.reference', 'ASC')
            ->setParameter('date', \DateTimeImmutable::createFromInterface($date), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();

        return $subAgreements;
    }
}

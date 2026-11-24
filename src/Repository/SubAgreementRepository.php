<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SubAgreement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * Gives all sub-agreements with their supplier. The sub-agreements that need a supplier come
     * first, because an administrator must give them a supplier. An ignored sub-agreement needs
     * no supplier. Each group is sorted by the reference.
     *
     * @return list<SubAgreement>
     */
    public function findAllNeedingSupplierFirst(): array
    {
        /** @var list<SubAgreement> $subAgreements */
        $subAgreements = $this->createQueryBuilder('subAgreement')
            ->select('subAgreement', 'supplier')
            ->leftJoin('subAgreement.supplier', 'supplier')
            ->addSelect('CASE WHEN subAgreement.supplier IS NULL AND subAgreement.ignored = false THEN 0 ELSE 1 END AS HIDDEN supplierGroup')
            ->orderBy('supplierGroup', 'ASC')
            ->addOrderBy('subAgreement.reference', 'ASC')
            ->getQuery()
            ->getResult();

        return $subAgreements;
    }
}

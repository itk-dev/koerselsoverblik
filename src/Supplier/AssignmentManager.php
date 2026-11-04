<?php

namespace App\Supplier;

use App\Entity\SubAgreementSupplierAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Saves and deletes the supplier assignments, and dispatches SubAgreementSupplierChanged for each
 * change.
 */
final readonly class AssignmentManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Creates or changes the assignment from valid form data.
     *
     * @throws \InvalidArgumentException if the data has no supplier or no first date
     */
    public function save(AssignmentDto $data): SubAgreementSupplierAssignment
    {
        if (null === $data->supplier || null === $data->validFrom) {
            throw new \InvalidArgumentException('The assignment needs a supplier and a first date.');
        }

        $assignment = $data->assignment;
        $oldPeriod = $assignment?->getPeriod();
        if (null === $assignment) {
            $assignment = new SubAgreementSupplierAssignment($data->subAgreement, $data->supplier, $data->validFrom, $data->validTo);
            $data->subAgreement->getAssignments()->add($assignment);
            $this->entityManager->persist($assignment);
        } else {
            $assignment->change($data->supplier, $data->validFrom, $data->validTo);
        }
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new SubAgreementSupplierChanged($data->subAgreement, $oldPeriod, $assignment->getPeriod()));

        return $assignment;
    }

    public function remove(SubAgreementSupplierAssignment $assignment): void
    {
        $subAgreement = $assignment->getSubAgreement();
        $oldPeriod = $assignment->getPeriod();

        $subAgreement->getAssignments()->removeElement($assignment);
        $this->entityManager->remove($assignment);
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new SubAgreementSupplierChanged($subAgreement, $oldPeriod, null));
    }
}

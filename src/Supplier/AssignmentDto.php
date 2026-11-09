<?php

declare(strict_types=1);

namespace App\Supplier;

use App\Entity\SubAgreement;
use App\Entity\SubAgreementSupplierAssignment;
use App\Entity\Supplier;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The data of the assignment form. The fields are empty until the form sets them, so the form does
 * not change an entity before the data is valid.
 */
#[NoOverlappingAssignment]
final class AssignmentDto
{
    #[Assert\NotNull]
    public ?Supplier $supplier = null;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $validFrom = null;

    #[Assert\GreaterThanOrEqual(propertyPath: 'validFrom', message: 'assignment.end_before_start')]
    public ?\DateTimeImmutable $validTo = null;

    /**
     * @param ?SubAgreementSupplierAssignment $assignment the changed assignment, or null for a new one
     */
    private function __construct(
        public readonly SubAgreement $subAgreement,
        public readonly ?SubAgreementSupplierAssignment $assignment,
    ) {
    }

    public static function forNew(SubAgreement $subAgreement): self
    {
        return new self($subAgreement, null);
    }

    public static function forChange(SubAgreementSupplierAssignment $assignment): self
    {
        $data = new self($assignment->getSubAgreement(), $assignment);
        $data->supplier = $assignment->getSupplier();
        $data->validFrom = $assignment->getValidFrom();
        $data->validTo = $assignment->getValidTo();

        return $data;
    }

    /**
     * Gives the period, or null without a first date.
     */
    public function period(): ?ValidityPeriod
    {
        return null === $this->validFrom ? null : new ValidityPeriod($this->validFrom, $this->validTo);
    }
}

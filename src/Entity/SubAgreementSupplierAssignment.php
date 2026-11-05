<?php

namespace App\Entity;

use App\Repository\SubAgreementSupplierAssignmentRepository;
use App\Supplier\ValidityPeriod;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The supplier that drives the routes of a sub-agreement from the first date to the last date. An
 * empty last date means no end. The periods of one sub-agreement do not overlap. AssignmentDto
 * validates a new or changed assignment. The database cannot prevent overlap. It only rejects two
 * assignments of one sub-agreement with the same first date.
 */
#[ORM\Entity(repositoryClass: SubAgreementSupplierAssignmentRepository::class)]
#[ORM\UniqueConstraint(columns: ['sub_agreement_id', 'valid_from'])]
class SubAgreementSupplierAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'assignments')]
        #[ORM\JoinColumn(nullable: false)]
        private SubAgreement $subAgreement,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Supplier $supplier,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $validFrom,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $validTo,
    ) {
    }

    public function change(Supplier $supplier, \DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo): void
    {
        $this->supplier = $supplier;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubAgreement(): SubAgreement
    {
        return $this->subAgreement;
    }

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function getValidFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function getPeriod(): ValidityPeriod
    {
        return new ValidityPeriod($this->validFrom, $this->validTo);
    }
}

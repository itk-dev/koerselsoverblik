<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SubAgreementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A sub-agreement (delaftale), for example "AB-CD". The routes of the sub-agreement have names that
 * start with the reference. An administrator gives the sub-agreement one supplier. The supplier
 * applies to all dates.
 */
#[ORM\Entity(repositoryClass: SubAgreementRepository::class)]
#[UniqueEntity('reference', message: 'sub_agreement.reference_used')]
class SubAgreement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $reference = '';

    /**
     * The import creates a sub-agreement with no supplier. Its routes count as "Unknown". The
     * foreign key has no "ON DELETE", so the database blocks the delete of a supplier with
     * sub-agreements.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Supplier $supplier = null;

    /**
     * @var Collection<int, Route>
     */
    #[ORM\OneToMany(targetEntity: Route::class, mappedBy: 'subAgreement')]
    #[ORM\OrderBy(['sourceKey' => 'ASC'])]
    private Collection $routes;

    public function __construct()
    {
        $this->routes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        // An empty field gives null. NotBlank then rejects the empty reference.
        $this->reference = trim($reference ?? '');

        return $this;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    /**
     * @return Collection<int, Route>
     */
    public function getRoutes(): Collection
    {
        return $this->routes;
    }
}

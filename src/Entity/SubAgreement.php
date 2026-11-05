<?php

namespace App\Entity;

use App\Repository\SubAgreementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A sub-agreement (delaftale), for example "Ø4-VB". The routes of the sub-agreement have names that
 * start with the reference. An administrator assigns the sub-agreement to a supplier for a period.
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
     * @var Collection<int, Route>
     */
    #[ORM\OneToMany(targetEntity: Route::class, mappedBy: 'subAgreement')]
    #[ORM\OrderBy(['sourceKey' => 'ASC'])]
    private Collection $routes;

    /**
     * @var Collection<int, SubAgreementSupplierAssignment>
     */
    #[ORM\OneToMany(targetEntity: SubAgreementSupplierAssignment::class, mappedBy: 'subAgreement')]
    #[ORM\OrderBy(['validFrom' => 'ASC'])]
    private Collection $assignments;

    public function __construct()
    {
        $this->routes = new ArrayCollection();
        $this->assignments = new ArrayCollection();
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

    /**
     * @return Collection<int, Route>
     */
    public function getRoutes(): Collection
    {
        return $this->routes;
    }

    /**
     * @return Collection<int, SubAgreementSupplierAssignment>
     */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    /**
     * Gives the assignment on the date, or null if the date has no assignment. If assignments
     * overlap, the latest first date wins, as in SubAgreementSupplierAssignmentRepository::findSupplier().
     * The collection is in the order of the first date, so the last match wins.
     */
    public function getAssignmentOn(\DateTimeInterface $date): ?SubAgreementSupplierAssignment
    {
        $found = null;
        foreach ($this->assignments as $assignment) {
            if ($assignment->getPeriod()->contains($date)) {
                $found = $assignment;
            }
        }

        return $found;
    }
}

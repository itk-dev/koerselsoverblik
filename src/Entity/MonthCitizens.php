<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MonthCitizensRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The number of citizens who were driven in one calendar month, for all suppliers or for one
 * supplier. It is derived from the stop rows, and holds no person ids. `MonthCitizensBuilder`
 * builds it again. See "Data model" in docs/architecture.md.
 */
#[ORM\Entity(repositoryClass: MonthCitizensRepository::class)]
#[ORM\UniqueConstraint(columns: ['first_date', 'supplier_id'])]
class MonthCitizens
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param \DateTimeImmutable $firstDate the first date of the month
     * @param ?Supplier          $supplier  the supplier, or null for all suppliers
     */
    public function __construct(
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $firstDate,
        // The rows of a supplier go with the supplier.
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Supplier $supplier,
        #[ORM\Column]
        private int $citizens,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstDate(): \DateTimeImmutable
    {
        return $this->firstDate;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function getCitizens(): int
    {
        return $this->citizens;
    }
}

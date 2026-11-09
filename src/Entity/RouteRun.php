<?php

namespace App\Entity;

use App\Import\RouteRunDto;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One route run: one route on one date with one block number. It is derived from the stop rows,
 * and `RouteBuilder` builds it again for each import. See "Data model" in docs/architecture.md.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['route_id', 'service_date', 'run'])]
#[ORM\Index(columns: ['service_date'])]
class RouteRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $firstPickupPlanned;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $firstPickupActual;

    #[ORM\Column(nullable: true)]
    private ?int $firstPickupDelaySeconds;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastDropPlanned;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastDropActual;

    #[ORM\Column(nullable: true)]
    private ?int $lastDropDelaySeconds;

    #[ORM\Column]
    private bool $cancelled;

    /**
     * @param int $run the block number in the extract
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Route $route,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $serviceDate,
        #[ORM\Column]
        private int $run,
        RouteRunDto $values,
    ) {
        $this->firstPickupPlanned = $values->firstPickupPlanned;
        $this->firstPickupActual = $values->firstPickupActual;
        $this->firstPickupDelaySeconds = $values->firstPickupDelaySeconds;
        $this->lastDropPlanned = $values->lastDropPlanned;
        $this->lastDropActual = $values->lastDropActual;
        $this->lastDropDelaySeconds = $values->lastDropDelaySeconds;
        $this->cancelled = $values->cancelled;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function getServiceDate(): \DateTimeImmutable
    {
        return $this->serviceDate;
    }

    public function getRun(): int
    {
        return $this->run;
    }

    public function getFirstPickupPlanned(): ?\DateTimeImmutable
    {
        return $this->firstPickupPlanned;
    }

    public function getFirstPickupActual(): ?\DateTimeImmutable
    {
        return $this->firstPickupActual;
    }

    public function getFirstPickupDelaySeconds(): ?int
    {
        return $this->firstPickupDelaySeconds;
    }

    public function getLastDropPlanned(): ?\DateTimeImmutable
    {
        return $this->lastDropPlanned;
    }

    public function getLastDropActual(): ?\DateTimeImmutable
    {
        return $this->lastDropActual;
    }

    public function getLastDropDelaySeconds(): ?int
    {
        return $this->lastDropDelaySeconds;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}

<?php

namespace App\Entity;

use App\Import\Extract\SourceCells;
use App\Import\Extract\StopRowDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Repository\StopRowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One stop from an extract, with the copy of its row (ADR 006). An import replaces all stop rows
 * of its date range. The person id is kept as it is in the extract (ADR 004).
 *
 * @phpstan-import-type SourceValue from SourceCells
 */
#[ORM\Entity(repositoryClass: StopRowRepository::class)]
#[ORM\UniqueConstraint(columns: ['mapping_name', 'row_key'])]
#[ORM\Index(columns: ['service_date'])]
class StopRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rowKey;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $serviceDate;

    #[ORM\Column]
    private int $run;

    #[ORM\Column(length: 16, enumType: StopType::class)]
    private StopType $stopType;

    #[ORM\Column(length: 16, enumType: StopStatus::class)]
    private StopStatus $stopStatus;

    #[ORM\Column(nullable: true)]
    private ?int $passengersAfter;

    #[ORM\Column(nullable: true)]
    private ?float $nextDistanceKm;

    #[ORM\Column(nullable: true)]
    private ?float $nextDurationMin;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $plannedTime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $actualTime;

    #[ORM\Column(nullable: true)]
    private ?bool $plannedSolo;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $personId;

    /**
     * @var array<array-key, SourceValue>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $sourceValues;

    public function __construct(
        StopRowDto $row,
        #[ORM\Column(length: 64)]
        private string $mappingName,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Route $route,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private ImportBatch $importBatch,
    ) {
        $this->rowKey = $row->rowKey;
        $this->serviceDate = $row->serviceDate;
        $this->run = $row->run;
        $this->stopType = $row->stopType;
        $this->stopStatus = $row->stopStatus;
        $this->passengersAfter = $row->passengersAfter;
        $this->nextDistanceKm = $row->nextDistanceKm;
        $this->nextDurationMin = $row->nextDurationMin;
        $this->plannedTime = $row->plannedTime;
        $this->actualTime = $row->actualTime;
        $this->plannedSolo = $row->plannedSolo;
        $this->personId = $row->personId;
        $this->sourceValues = $row->sourceValues;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMappingName(): string
    {
        return $this->mappingName;
    }

    public function getRowKey(): ?string
    {
        return $this->rowKey;
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

    public function getStopType(): StopType
    {
        return $this->stopType;
    }

    public function getStopStatus(): StopStatus
    {
        return $this->stopStatus;
    }

    public function getPassengersAfter(): ?int
    {
        return $this->passengersAfter;
    }

    public function getNextDistanceKm(): ?float
    {
        return $this->nextDistanceKm;
    }

    public function getNextDurationMin(): ?float
    {
        return $this->nextDurationMin;
    }

    public function getPlannedTime(): \DateTimeImmutable
    {
        return $this->plannedTime;
    }

    public function getActualTime(): ?\DateTimeImmutable
    {
        return $this->actualTime;
    }

    public function getPlannedSolo(): ?bool
    {
        return $this->plannedSolo;
    }

    public function getPersonId(): ?string
    {
        return $this->personId;
    }

    /**
     * @return array<array-key, SourceValue>
     */
    public function getSourceValues(): array
    {
        return $this->sourceValues;
    }

    public function getImportBatch(): ImportBatch
    {
        return $this->importBatch;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Import\Extract\StopRowDto;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Repository\StopRowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One stop from an extract, with the copy of its row (ADR 006). An import replaces all stop rows
 * of its date range. The person id is kept as it is in the extract (ADR 004).
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

    /**
     * The actual time minus the planned time. The import calculates it in PHP, because SQL gives
     * the wall-clock difference of two local times (ADR 005).
     */
    #[ORM\Column(nullable: true)]
    private ?int $delaySeconds;

    #[ORM\Column(nullable: true)]
    private ?bool $plannedSolo;

    #[ORM\Column(nullable: true)]
    private ?int $passengers;

    #[ORM\Column(nullable: true)]
    private ?float $pricePerMinute;

    #[ORM\Column(nullable: true)]
    private ?float $cancellationNoticeMin;

    #[ORM\Column(nullable: true)]
    private ?bool $cancellationInTime;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $locationName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $personId;

    /**
     * The owning side gives a lazy object, so Doctrine does not read the copy with the row. The
     * detach of the row also detaches the copy.
     */
    #[ORM\OneToOne(cascade: ['persist', 'detach'])]
    #[ORM\JoinColumn(nullable: false)]
    private SourceRow $sourceRow;

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
        $this->delaySeconds = $row->delaySeconds;
        $this->plannedSolo = $row->plannedSolo;
        $this->passengers = $row->passengers;
        $this->pricePerMinute = $row->pricePerMinute;
        $this->cancellationNoticeMin = $row->cancellationNoticeMin;
        $this->cancellationInTime = $row->cancellationInTime;
        $this->locationName = $row->locationName;
        $this->personId = $row->personId;
        $this->sourceRow = new SourceRow($row->sourceValues);
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

    public function getDelaySeconds(): ?int
    {
        return $this->delaySeconds;
    }

    public function getPlannedSolo(): ?bool
    {
        return $this->plannedSolo;
    }

    public function getPassengers(): ?int
    {
        return $this->passengers;
    }

    public function getPricePerMinute(): ?float
    {
        return $this->pricePerMinute;
    }

    public function getCancellationNoticeMin(): ?float
    {
        return $this->cancellationNoticeMin;
    }

    public function getCancellationInTime(): ?bool
    {
        return $this->cancellationInTime;
    }

    public function getLocationName(): ?string
    {
        return $this->locationName;
    }

    public function getPersonId(): ?string
    {
        return $this->personId;
    }

    public function getSourceRow(): SourceRow
    {
        return $this->sourceRow;
    }

    public function getImportBatch(): ImportBatch
    {
        return $this->importBatch;
    }
}

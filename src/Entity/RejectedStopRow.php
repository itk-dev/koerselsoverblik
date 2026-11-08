<?php

namespace App\Entity;

use App\Import\Extract\RejectedRowDto;
use App\Import\Extract\RejectionReason;
use App\Import\Extract\SourceCells;
use App\Import\Mapping\FieldName;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A row of an extract that the import rejects, with the copy of the row (ADR 006). A later read
 * through a corrected mapping can import it. A row outside the range of the upload is not stored.
 *
 * @phpstan-import-type SourceValue from SourceCells
 */
#[ORM\Entity]
#[ORM\Index(columns: ['service_date'])]
class RejectedStopRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // "row_number" is a reserved word in MariaDB, and Doctrine does not quote it.
    #[ORM\Column(name: 'sheet_row_number')]
    private int $rowNumber;

    #[ORM\Column(length: 32, enumType: RejectionReason::class)]
    private RejectionReason $reason;

    #[ORM\Column(length: 32, nullable: true, enumType: FieldName::class)]
    private ?FieldName $field;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $serviceDate;

    /**
     * @var array<array-key, SourceValue>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $sourceValues;

    public function __construct(
        RejectedRowDto $row,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private ImportBatch $importBatch,
    ) {
        $this->rowNumber = $row->rowNumber;
        $this->reason = $row->reason;
        $this->field = $row->field;
        $this->serviceDate = $row->serviceDate;
        $this->sourceValues = $row->sourceValues;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImportBatch(): ImportBatch
    {
        return $this->importBatch;
    }

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    public function getReason(): RejectionReason
    {
        return $this->reason;
    }

    public function getField(): ?FieldName
    {
        return $this->field;
    }

    public function getServiceDate(): ?\DateTimeImmutable
    {
        return $this->serviceDate;
    }

    /**
     * @return array<array-key, SourceValue>
     */
    public function getSourceValues(): array
    {
        return $this->sourceValues;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Import\Extract\RejectedRowDto;
use App\Import\Extract\RejectionReason;
use App\Import\Mapping\FieldName;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A row of an extract that the import rejects, with the copy of the row (ADR 006). A later read
 * through a corrected mapping can import it. A row outside the range of the upload is not stored.
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
     * The owning side gives a lazy object, so Doctrine does not read the copy with the row. The
     * detach of the row also detaches the copy.
     */
    #[ORM\OneToOne(cascade: ['persist', 'detach'])]
    #[ORM\JoinColumn(nullable: false)]
    private SourceRow $sourceRow;

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
        $this->sourceRow = new SourceRow($row->sourceValues);
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

    public function getSourceRow(): SourceRow
    {
        return $this->sourceRow;
    }
}

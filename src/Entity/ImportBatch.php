<?php

declare(strict_types=1);

namespace App\Entity;

use App\Import\Extract\RejectionCountDto;
use App\Import\ImportRangeDto;
use App\Import\ImportResultDto;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One import of driving data: the mapping, the file, who and when, the replaced date range and the
 * counts. The errors and the warnings are groups of field and reason. They have no values from the
 * extract.
 *
 * @phpstan-type GroupData array{field: ?string, reason: string, count: int, row_numbers: list<int>}
 */
#[ORM\Entity]
class ImportBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $firstDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $lastDate;

    #[ORM\Column]
    private int $rowsDeleted = 0;

    #[ORM\Column]
    private int $rowsInserted = 0;

    #[ORM\Column]
    private int $rowsRejected = 0;

    #[ORM\Column]
    private int $warningCount = 0;

    /**
     * @var list<GroupData>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $errors = [];

    /**
     * @var list<GroupData>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $warnings = [];

    /**
     * @param ?User $uploadedBy the user of the upload page, or null for the console
     */
    public function __construct(
        #[ORM\Column(length: 64)]
        private string $mappingName,
        #[ORM\Column(length: 255)]
        private string $fileName,
        // A deleted user leaves the batch with no user, as the console does.
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?User $uploadedBy,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $uploadedAt,
        ImportRangeDto $range,
    ) {
        $this->firstDate = $range->firstDate;
        $this->lastDate = $range->lastDate;
    }

    public function complete(ImportResultDto $result): void
    {
        $this->rowsDeleted = $result->deletedRows;
        $this->rowsInserted = $result->insertedRows;
        $this->rowsRejected = $result->rejectedRows;
        $this->warningCount = $result->warningCount;
        $this->errors = array_map(self::groupData(...), $result->rejections);
        $this->warnings = array_map(self::groupData(...), $result->warnings);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMappingName(): string
    {
        return $this->mappingName;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function getFirstDate(): \DateTimeImmutable
    {
        return $this->firstDate;
    }

    public function getLastDate(): \DateTimeImmutable
    {
        return $this->lastDate;
    }

    public function getRowsDeleted(): int
    {
        return $this->rowsDeleted;
    }

    public function getRowsInserted(): int
    {
        return $this->rowsInserted;
    }

    public function getRowsRejected(): int
    {
        return $this->rowsRejected;
    }

    public function getWarningCount(): int
    {
        return $this->warningCount;
    }

    /**
     * @return list<GroupData>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<GroupData>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return GroupData
     */
    private static function groupData(RejectionCountDto $group): array
    {
        return [
            'field' => $group->field?->value,
            'reason' => $group->reason->value,
            'count' => $group->count,
            'row_numbers' => $group->rowNumbers,
        ];
    }
}

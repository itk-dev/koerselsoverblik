<?php

declare(strict_types=1);

namespace App\Entity;

use App\Import\Extract\SourceCells;
use App\Repository\SourceRowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The copy of one row of an extract (ADR 006). A stop row or a rejected row refers to it. The copy
 * is in its own table, so that Doctrine does not read it each time that it reads a row.
 *
 * @phpstan-import-type SourceValue from SourceCells
 */
#[ORM\Entity(repositoryClass: SourceRowRepository::class)]
class SourceRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param array<array-key, SourceValue> $values keyed by the column name
     */
    public function __construct(
        // "values" is a reserved word in MariaDB, and Doctrine does not quote it.
        #[ORM\Column(name: 'source_values', type: Types::JSON)]
        private array $values,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return array<array-key, SourceValue>
     */
    public function getValues(): array
    {
        return $this->values;
    }
}

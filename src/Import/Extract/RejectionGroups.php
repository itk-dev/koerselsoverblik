<?php

namespace App\Import\Extract;

use App\Import\Mapping\FieldName;

/**
 * Counts rejected rows or warnings in groups of the same field and reason. A group keeps the first
 * row numbers, so that a person can find the rows. It keeps no values.
 */
final class RejectionGroups
{
    /**
     * The number of row numbers to keep for each group.
     */
    private const int ROW_NUMBERS = 10;

    /**
     * @var array<string, RejectionCountDto> keyed by the field and the reason, in the order of the first row of each group
     */
    private array $groups = [];

    /**
     * @param ?FieldName $field the field, or null for the row id
     */
    public function add(?FieldName $field, RejectionReason $reason, int $rowNumber): void
    {
        $key = implode('/', [$field?->value, $reason->value]);
        $group = $this->groups[$key] ?? new RejectionCountDto($field, $reason, 0, []);
        $this->groups[$key] = new RejectionCountDto(
            $field,
            $reason,
            $group->count + 1,
            \count($group->rowNumbers) < self::ROW_NUMBERS ? [...$group->rowNumbers, $rowNumber] : $group->rowNumbers,
        );
    }

    /**
     * @return list<RejectionCountDto>
     */
    public function groups(): array
    {
        return array_values($this->groups);
    }

    /**
     * Gives the number of rows in all the groups.
     */
    public function total(): int
    {
        return array_sum(array_map(static fn (RejectionCountDto $group): int => $group->count, $this->groups));
    }
}

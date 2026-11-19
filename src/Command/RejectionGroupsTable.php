<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\Extract\RejectionCountDto;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows the rejected rows or the warnings of an extract in the console, with no values.
 */
final class RejectionGroupsTable
{
    /**
     * Shows the rows with the same field and reason as one line. Shows nothing when there are no
     * groups.
     *
     * @param list<RejectionCountDto> $groups
     */
    public static function show(SymfonyStyle $io, string $title, array $groups): void
    {
        if ([] === $groups) {
            return;
        }

        $io->section($title);
        $io->table(
            ['field', 'reason', 'rows', 'first row numbers'],
            array_map(static fn (RejectionCountDto $group): array => [
                $group->field->value ?? '(row id)',
                $group->reason->value,
                $group->count,
                implode(', ', $group->rowNumbers),
            ], $groups),
        );
    }
}

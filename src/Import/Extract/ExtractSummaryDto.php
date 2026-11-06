<?php

namespace App\Import\Extract;

use App\Import\Mapping\FieldName;

/**
 * The result of a parse, counted for the validate command. It has no values of the rejected rows
 * or of the warnings.
 */
final readonly class ExtractSummaryDto
{
    /**
     * The number of row numbers to keep for each group of rejected rows or warnings.
     */
    private const int ROW_NUMBERS = 10;

    /**
     * @param list<RejectionCountDto> $rejections in the order of the first row of each group
     * @param list<StopRowDto>        $preview
     * @param list<string>            $dates      the different service dates of the stop rows, as Y-m-d, sorted
     * @param list<RejectionCountDto> $warnings   the warnings of the stop rows, grouped in the same way as the rejections
     */
    private function __construct(
        public int $stopRows,
        public int $rejectedRows,
        public array $rejections,
        public array $preview,
        public array $dates,
        public int $warningCount,
        public array $warnings,
    ) {
    }

    /**
     * @param iterable<StopRowDto|RejectedRowDto> $rows
     * @param int                                 $previewSize the number of stop rows in the preview
     */
    public static function fromRows(iterable $rows, int $previewSize): self
    {
        $stopRows = 0;
        $preview = [];
        /** @var array<string, string> $dates the key makes each date different */
        $dates = [];
        /** @var array<string, RejectionCountDto> $rejections */
        $rejections = [];
        /** @var array<string, RejectionCountDto> $warnings */
        $warnings = [];

        foreach ($rows as $row) {
            if ($row instanceof RejectedRowDto) {
                self::count($rejections, $row->field, $row->reason, $row->rowNumber);
                continue;
            }

            foreach ($row->warnings as $warning) {
                self::count($warnings, $warning->field, $warning->reason, $row->rowNumber);
            }
            ++$stopRows;
            if (\count($preview) < $previewSize) {
                $preview[] = $row;
            }
            $date = $row->serviceDate->format('Y-m-d');
            $dates[$date] = $date;
        }

        $rejections = array_values($rejections);
        $warnings = array_values($warnings);
        // sort() also gives the dates new keys from 0.
        sort($dates);

        return new self(
            $stopRows,
            self::total($rejections),
            $rejections,
            $preview,
            $dates,
            self::total($warnings),
            $warnings,
        );
    }

    /**
     * Adds one row to the group with the field and the reason.
     *
     * @param array<string, RejectionCountDto> $groups
     */
    private static function count(array &$groups, ?FieldName $field, RejectionReason $reason, int $rowNumber): void
    {
        $key = implode('/', [$field?->value, $reason->value]);
        $group = $groups[$key] ?? new RejectionCountDto($field, $reason, 0, []);
        $groups[$key] = new RejectionCountDto(
            $field,
            $reason,
            $group->count + 1,
            \count($group->rowNumbers) < self::ROW_NUMBERS ? [...$group->rowNumbers, $rowNumber] : $group->rowNumbers,
        );
    }

    /**
     * @param list<RejectionCountDto> $groups
     */
    private static function total(array $groups): int
    {
        return array_sum(array_map(static fn (RejectionCountDto $group): int => $group->count, $groups));
    }
}

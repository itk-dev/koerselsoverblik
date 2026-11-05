<?php

namespace App\Import\Extract;

/**
 * The result of a parse, counted for the validate command. It has no values of the rejected rows.
 */
final readonly class ExtractSummaryDto
{
    /**
     * The number of row numbers to keep for each group of rejected rows.
     */
    private const int ROW_NUMBERS = 10;

    /**
     * @param list<RejectionCountDto> $rejections in the order of the first row of each group
     * @param list<StopRowDto>        $preview
     * @param list<string>            $dates      the different service dates of the stop rows, as Y-m-d, sorted
     */
    private function __construct(
        public int $stopRows,
        public int $rejectedRows,
        public array $rejections,
        public array $preview,
        public array $dates,
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

        foreach ($rows as $row) {
            if ($row instanceof RejectedRowDto) {
                $key = implode('/', [$row->field?->value, $row->reason->value]);
                $group = $rejections[$key] ?? new RejectionCountDto($row->field, $row->reason, 0, []);
                $rejections[$key] = new RejectionCountDto(
                    $row->field,
                    $row->reason,
                    $group->count + 1,
                    \count($group->rowNumbers) < self::ROW_NUMBERS ? [...$group->rowNumbers, $row->rowNumber] : $group->rowNumbers,
                );
                continue;
            }

            ++$stopRows;
            if (\count($preview) < $previewSize) {
                $preview[] = $row;
            }
            $date = $row->serviceDate->format('Y-m-d');
            $dates[$date] = $date;
        }

        $rejections = array_values($rejections);
        // sort() also gives the dates new keys from 0.
        sort($dates);

        return new self(
            $stopRows,
            array_sum(array_map(static fn (RejectionCountDto $group): int => $group->count, $rejections)),
            $rejections,
            $preview,
            $dates,
        );
    }
}

<?php

namespace App\Import\Extract;

/**
 * The result of a parse, counted for the validate command. It has no values of the rejected rows
 * or of the warnings.
 */
final readonly class ExtractSummaryDto
{
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
        $rejections = new RejectionGroups();
        $warnings = new RejectionGroups();

        foreach ($rows as $row) {
            if ($row instanceof RejectedRowDto) {
                $rejections->add($row->field, $row->reason, $row->rowNumber);
                continue;
            }

            foreach ($row->warnings as $warning) {
                $warnings->add($warning->field, $warning->reason, $row->rowNumber);
            }
            ++$stopRows;
            if (\count($preview) < $previewSize) {
                $preview[] = $row;
            }
            $date = $row->serviceDate->format('Y-m-d');
            $dates[$date] = $date;
        }

        // sort() also gives the dates new keys from 0.
        sort($dates);

        return new self(
            $stopRows,
            $rejections->total(),
            $rejections->groups(),
            $preview,
            $dates,
            $warnings->total(),
            $warnings->groups(),
        );
    }
}

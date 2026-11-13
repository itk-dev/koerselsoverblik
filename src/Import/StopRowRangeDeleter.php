<?php

declare(strict_types=1);

namespace App\Import;

use App\Repository\RejectedStopRowRepository;
use App\Repository\SourceRowRepository;
use App\Repository\StopRowRepository;

/**
 * Deletes the stop rows and the rejected rows of a range, with their copies (ADR 006). The caller
 * runs the delete in a transaction.
 */
final readonly class StopRowRangeDeleter
{
    public function __construct(
        private StopRowRepository $stopRows,
        private RejectedStopRowRepository $rejectedRows,
        private SourceRowRepository $sourceRows,
    ) {
    }

    /**
     * A DQL delete does not cascade. Thus the deleter reads the ids of the copies before it deletes
     * the rows, and deletes the copies after the rows. The delete of the copies uses their ids, so
     * it does not read the copies of other dates.
     *
     * @return int the number of deleted stop rows
     */
    public function deleteRange(ImportRangeDto $range): int
    {
        $copies = [
            ...$this->rejectedRows->sourceRowIdsInRange($range),
            ...$this->stopRows->sourceRowIdsInRange($range),
        ];

        $this->rejectedRows->deleteInRange($range);
        $deleted = $this->stopRows->deleteInRange($range);
        $this->sourceRows->deleteByIds($copies);

        return $deleted;
    }
}

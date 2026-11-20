<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\RejectedStopRow;
use App\Entity\StopRow;
use App\Import\ImportRangeDto;
use App\Repository\RejectedStopRowRepository;
use App\Repository\StopRowRepository;
use Doctrine\ORM\Query;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * Gives one page of the stop rows and of the rejected rows of a range. Each list has its own page
 * number in the query, so the two lists page separately.
 */
final readonly class DrivingDataRows
{
    public const int PAGE_SIZE = 100;

    public function __construct(
        private PaginatorInterface $paginator,
        private StopRowRepository $stopRows,
        private RejectedStopRowRepository $rejectedRows,
    ) {
    }

    /**
     * @return PaginationInterface<int, StopRow>
     */
    public function stopRows(ImportRangeDto $range, ?string $route, int $page): PaginationInterface
    {
        /** @var PaginationInterface<int, StopRow> $pagination */
        $pagination = $this->page($this->stopRows->listInRange($range, $route), $page, 'stopPage');

        return $pagination;
    }

    /**
     * @return PaginationInterface<int, RejectedStopRow>
     */
    public function rejectedRows(ImportRangeDto $range, int $page): PaginationInterface
    {
        /** @var PaginationInterface<int, RejectedStopRow> $pagination */
        $pagination = $this->page($this->rejectedRows->listInRange($range), $page, 'rejectedPage');

        return $pagination;
    }

    /**
     * A query can neither sort nor filter the list. A filter on a field that does not show, for
     * example the person id, would tell if a row has a value.
     *
     * @param Query<null, StopRow|RejectedStopRow> $query
     *
     * @return PaginationInterface<int, mixed>
     */
    private function page(Query $query, int $page, string $pageName): PaginationInterface
    {
        return $this->paginator->paginate($query, max(1, $page), self::PAGE_SIZE, [
            PaginatorInterface::PAGE_PARAMETER_NAME => $pageName,
            PaginatorInterface::SORT_FIELD_PARAMETER_NAME => null,
            PaginatorInterface::SORT_DIRECTION_PARAMETER_NAME => null,
            PaginatorInterface::FILTER_FIELD_ALLOW_LIST => [],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\DrivingDataRows;
use App\Admin\RowsQueryDto;
use App\Admin\SourceRowView;
use App\Clock\LocalCalendar;
use App\Entity\RejectedStopRow;
use App\Entity\StopRow;
use App\Form\RowsQueryType;
use App\Import\Mapping\InvalidMappingException;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingProvider;
use App\Import\UploadedDates;
use App\Report\MonthDto;
use App\Repository\ImportBatchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Shows the stored driving data: the uploaded dates of a month, the stop rows and the rejected
 * rows of a range, and the copy of one row. The person id never shows. See "Driving data" in the
 * README.
 */
#[IsGranted('ROLE_MANAGE_DRIVING_DATA')]
#[Route('/admin/driving-data')]
final class DrivingDataController extends AbstractController
{
    /**
     * @param int $maxDays the value of APP_DRIVING_DATA_MAX_DAYS
     */
    public function __construct(
        private readonly UploadedDates $uploadedDates,
        private readonly ImportBatchRepository $batches,
        private readonly LocalCalendar $calendar,
        private readonly DrivingDataRows $rows,
        private readonly MappingProvider $mappings,
        #[Autowire(param: 'app.driving_data_max_days')]
        private readonly int $maxDays,
    ) {
    }

    /**
     * The uploaded dates of one month. A month that is not valid or that is after the current
     * month gives the current month.
     */
    #[Route('', name: 'admin_driving_data', methods: ['GET'])]
    public function index(
        #[MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]
        ?string $month = null,
    ): Response {
        $today = $this->calendar->today();
        $shown = MonthDto::fromQuery($month, $today);
        $firstDate = $this->batches->firstDate();

        return $this->render('admin/driving_data/index.html.twig', [
            'month' => $shown,
            'isCurrentMonth' => !$shown->isBefore(MonthDto::of($today)),
            // No driving data is before the first batch, so the month before it has no link.
            'isFirstMonth' => null === $firstDate || !MonthDto::of($firstDate)->isBefore($shown),
            'dates' => $this->uploadedDates->forMonth($shown),
        ]);
    }

    /**
     * The stop rows and the rejected rows of a range. With no range in the query, the range is
     * yesterday.
     */
    #[Route('/rows', name: 'admin_driving_data_rows', methods: ['GET'])]
    public function rows(
        Request $request,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, flags: \FILTER_NULL_ON_FAILURE)]
        ?int $stopPage = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, flags: \FILTER_NULL_ON_FAILURE)]
        ?int $rejectedPage = null,
    ): Response {
        $query = RowsQueryDto::forDate($this->calendar->today()->modify('-1 day'));
        $form = $this->createForm(RowsQueryType::class, $query);
        // A form with no name submits on each GET request. A request with no range shows the default range.
        if ($request->query->has('firstDate') || $request->query->has('lastDate')) {
            $form->handleRequest($request);
        }

        $valid = !$form->isSubmitted() || $form->isValid();
        $range = $valid ? $query->range($this->maxDays) : null;

        return $this->render('admin/driving_data/rows.html.twig', [
            'form' => $form,
            'stopRows' => null === $range ? null : $this->rows->stopRows($range, $query->route, $stopPage ?? 1),
            'rejectedRows' => null === $range ? null : $this->rows->rejectedRows($range, $rejectedPage ?? 1),
        ]);
    }

    #[Route('/stop-row/{id}', name: 'admin_driving_data_stop_row', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function stopRow(StopRow $row): Response
    {
        return $this->render('admin/driving_data/stop_row.html.twig', [
            'row' => $row,
            'copy' => SourceRowView::forStopRow($row->getSourceRow()->getValues(), $this->mapping($row->getMappingName())),
        ]);
    }

    #[Route('/rejected-row/{id}', name: 'admin_driving_data_rejected_row', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function rejectedRow(RejectedStopRow $row): Response
    {
        $batch = $row->getImportBatch();

        return $this->render('admin/driving_data/rejected_row.html.twig', [
            'row' => $row,
            'copy' => SourceRowView::forRejectedRow($row->getSourceRow()->getValues(), $this->mapping($batch->getMappingName()), $row->getField()),
        ]);
    }

    /**
     * Gives null if the mapping no longer exists. Then the page cannot find the person id column.
     */
    private function mapping(string $name): ?MappingDto
    {
        try {
            return $this->mappings->get($name);
        } catch (InvalidMappingException) {
            return null;
        }
    }
}

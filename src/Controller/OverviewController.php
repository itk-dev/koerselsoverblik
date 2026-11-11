<?php

declare(strict_types=1);

namespace App\Controller;

use App\Clock\LocalCalendar;
use App\Report\OverviewPageBuilder;
use App\Report\PeriodFilter;
use App\Report\PeriodRangeFactory;
use App\Report\PunctualityPageBuilder;
use App\Report\SupplierTableCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The overview: the key figures of the selected period, compared with the comparison period.
 */
#[IsGranted('ROLE_VIEW_OVERVIEW')]
final class OverviewController extends AbstractController
{
    /**
     * The filters are in the query string. An invalid value gives null, and then its default.
     */
    #[Route('/', name: 'overview', methods: ['GET'])]
    public function __invoke(
        OverviewPageBuilder $builder,
        PunctualityPageBuilder $punctualityBuilder,
        SupplierTableCalculator $supplierTable,
        PeriodRangeFactory $periods,
        LocalCalendar $calendar,
        #[Autowire(param: 'app.punctuality_tolerance_minutes')]
        int $toleranceMinutes,
        #[MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]
        ?string $p = null,
        #[MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]
        ?string $d = null,
        #[MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]
        ?string $c = null,
        #[MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]
        ?string $s = null,
    ): Response {
        $today = $calendar->today();
        $selection = $periods->create(PeriodFilter::fromQuery($p, $d, $c, $s, $today), $today);
        $punctuality = $punctualityBuilder->build($selection);

        return $this->render('overview/index.html.twig', [
            'selection' => $selection,
            'page' => $builder->build($selection),
            'punctuality' => $punctuality,
            'suppliers' => $supplierTable->calculate($selection->selectedRange, $punctuality->current()),
            'tolerance_minutes' => $toleranceMinutes,
        ]);
    }
}

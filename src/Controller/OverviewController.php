<?php

declare(strict_types=1);

namespace App\Controller;

use App\Clock\LocalCalendar;
use App\Report\MonthDto;
use App\Report\OverviewPageBuilder;
use App\Report\PunctualityPageBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The overview: the key figures of a month, with the months before it.
 */
#[IsGranted('ROLE_VIEW_OVERVIEW')]
final class OverviewController extends AbstractController
{
    /**
     * @param ?string $month the month as YYYY-MM. An invalid value gives null, and then the current month.
     */
    #[Route('/', name: 'overview', methods: ['GET'])]
    public function __invoke(
        OverviewPageBuilder $builder,
        PunctualityPageBuilder $punctualityBuilder,
        LocalCalendar $calendar,
        #[Autowire(param: 'app.punctuality_tolerance_minutes')]
        int $toleranceMinutes,
        #[MapQueryParameter(flags: \FILTER_NULL_ON_FAILURE)]
        ?string $month = null,
    ): Response {
        $today = $calendar->today();
        $selected = MonthDto::fromQuery($month, $today);

        return $this->render('overview/index.html.twig', [
            'page' => $builder->build($selected),
            'punctuality' => $punctualityBuilder->build($selected),
            'has_next_month' => $selected->isBefore(MonthDto::fromQuery(null, $today)),
            'tolerance_minutes' => $toleranceMinutes,
        ]);
    }
}

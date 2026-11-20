<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Clock\IsoDate;
use App\Entity\User;
use App\Import\DrivingDataClearer;
use App\Import\ImportRangeDto;
use App\Report\MonthDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Clears the driving data of one date from the list of uploaded dates on the driving data page. See
 * "Clear driving data" in the README.
 */
#[IsGranted('ROLE_MANAGE_DRIVING_DATA')]
final class ClearDrivingDataController extends AbstractController
{
    /**
     * @param int $maxDays the value of APP_DRIVING_DATA_MAX_DAYS
     */
    public function __construct(
        private readonly DrivingDataClearer $clearer,
        #[Autowire(param: 'app.driving_data_max_days')]
        private readonly int $maxDays,
    ) {
    }

    #[Route('/admin/driving-data/clear/{date}', name: 'admin_driving_data_clear', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['POST'])]
    public function __invoke(
        Request $request,
        string $date,
        // A request with no user fails, so that the log never shows a web clear as a console clear.
        #[CurrentUser]
        User $user,
    ): Response {
        $day = IsoDate::parse($date);
        if (null === $day) {
            throw $this->createNotFoundException('The date is not valid.');
        }
        // The token is for one date, so a form of one date cannot clear another date.
        if (!$this->isCsrfTokenValid('clear-'.$date, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('The CSRF token is not valid.');
        }

        $cleared = $this->clearer->clear(ImportRangeDto::create($day, $day, $this->maxDays), $user->getUserIdentifier());

        $this->addFlash('success', new TranslatableMessage('upload.cleared', [
            'day' => $day->format('j'),
            'month' => new TranslatableMessage('month.long.'.$day->format('n')),
            'year' => $day->format('Y'),
            'stopRows' => $cleared->stopRows,
            'routeRuns' => $cleared->routeRuns,
            'rejectedRows' => $cleared->rejectedRows,
        ]));

        return $this->redirectToRoute('admin_driving_data', ['month' => MonthDto::of($day)->query()]);
    }
}

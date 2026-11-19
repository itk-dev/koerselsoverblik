<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Repository\OtherToleranceException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Shows a page that tells the admin to run the command, when the totals of each route and day
 * were built with another tolerance. The default error page tells nothing, so somebody must first
 * read the log.
 */
#[AsEventListener(
    event: KernelEvents::EXCEPTION,
    method: 'onKernelException',
    // After the error listener logs the exception, and before it renders the error page. See
    // OidcLoginFailureListener.
    priority: -64,
)]
final readonly class OtherToleranceListener
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$event->isMainRequest() || !$exception instanceof OtherToleranceException) {
            return;
        }

        $content = $this->twig->render('error/other_tolerance.html.twig', [
            'tolerance_minutes' => $exception->toleranceMinutes,
            'command' => OtherToleranceException::COMMAND,
        ]);

        // The pages come back when the admin has run the command.
        $response = new Response($content, Response::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('Cache-Control', 'no-store, private');

        $event->setResponse($response);
    }
}

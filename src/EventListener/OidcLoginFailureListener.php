<?php

namespace App\EventListener;

use App\Security\OidcAuthenticator;
use ItkDev\OpenIdConnectBundle\Exception\AuthenticationFailedException;
use ItkDev\OpenIdConnectBundle\Exception\ProviderErrorException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Shows a failed OIDC login as a page, not as the default error page.
 *
 * The bundle throws AuthenticationFailedException on a failed callback. It is not an
 * AuthenticationException on purpose, so that the firewall does not send the user back to the
 * identity provider in a loop. ProviderErrorException is the subclass for a login that the
 * identity provider refused.
 */
#[AsEventListener(
    event: KernelEvents::EXCEPTION,
    method: 'onKernelException',
    // After the error listener logs the exception (0), and before it renders the error page (-128).
    priority: -64,
)]
final readonly class OidcLoginFailureListener
{
    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$event->isMainRequest() || !$exception instanceof AuthenticationFailedException) {
            return;
        }

        // The page does not show the exception message. It holds the identity provider's text,
        // which belongs in the log.
        $content = $this->twig->render('security/login_failed.html.twig', [
            'login_url' => $this->urlGenerator->generate('itkdev_openid_connect_login', [
                'providerKey' => OidcAuthenticator::PROVIDER_KEY,
            ]),
            'declined' => $exception instanceof ProviderErrorException
                && ProviderErrorException::ACCESS_DENIED === $exception->getError(),
        ]);

        // A refusal has its own status, for example 403. Anything else is an error on this side.
        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        $response = new Response($content, $status);
        $response->headers->set('Cache-Control', 'no-store, private');

        $event->setResponse($response);
    }
}

<?php

namespace App\Security;

use ItkDev\OpenIdConnect\Exception\OpenIdConnectExceptionInterface;
use ItkDev\OpenIdConnect\Exception\ValidationException;
use ItkDev\OpenIdConnectBundle\EventSubscriber\AuthenticationAuditSubscriber;
use ItkDev\OpenIdConnectBundle\Exception\AuthenticationFailedException;
use ItkDev\OpenIdConnectBundle\Exception\ProviderErrorException;
use ItkDev\OpenIdConnectBundle\Log\AuthenticationAuditLogger;
use ItkDev\OpenIdConnectBundle\Security\OpenIdConfigurationProviderManager;
use ItkDev\OpenIdConnectBundle\Security\OpenIdLoginAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * The OIDC login. The bundle validates the callback, and OidcLogin creates or updates the user.
 */
final class OidcAuthenticator extends OpenIdLoginAuthenticator
{
    public const string PROVIDER_KEY = 'default';

    public function __construct(
        OpenIdConfigurationProviderManager $providerManager,
        private readonly OidcLogin $oidcLogin,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthenticationAuditLogger $auditLogger,
    ) {
        parent::__construct($providerManager);
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $user = $this->oidcLogin->login($this->validateClaims($request));
        } catch (OpenIdConnectExceptionInterface $exception) {
            // Chained, so that onAuthenticationFailure() in the bundle reads the cause and throws
            // AuthenticationFailedException. OidcLoginFailureListener shows it as a page.
            throw new CustomUserMessageAuthenticationException($exception->getMessage(), previous: $exception);
        }

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn () => $user));
    }

    /**
     * Writes the failure to the authentication log, and gives a 4xx status to a failure that is
     * not a fault of the application.
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Only a callback after the redirect gets here. A login that fails in the identity
        // provider does not come back. Thus this records the failures at the application step,
        // for example an invalid state, a failed token request or a missing upn.
        //
        // The bundle throws in onAuthenticationFailure(). AuthenticatorManager calls it before it
        // dispatches LoginFailureEvent, so the audit subscriber of the bundle never sees these
        // failures. Write the record here. Remove this when the bundle records the failure.
        $provider = $request->attributes->get(AuthenticationAuditSubscriber::PROVIDER_ATTRIBUTE);
        $this->auditLogger->loginFailed(
            AuthenticationAuditLogger::METHOD_OIDC,
            self::class,
            null,
            is_string($provider) ? $provider : null,
            null,
            $request->getClientIp(),
            $exception->getPrevious()?->getMessage() ?? $exception->getMessage(),
        );

        try {
            return parent::onAuthenticationFailure($request, $exception);
        } catch (AuthenticationFailedException $failure) {
            $status = self::rejectedStatus($failure);

            throw null === $status ? $failure : new OidcCallbackRejectedException($status, $failure);
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return $this->createTargetPathRedirect($request, $firewallName, $this->urlGenerator->generate('overview'));
    }

    /**
     * Sends anonymous users to the bundle's login route, which redirects to the identity provider.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('itkdev_openid_connect_login', [
            'providerKey' => self::PROVIDER_KEY,
        ]));
    }

    /**
     * The 4xx status of a failure that is not a fault of the application, or null.
     */
    private static function rejectedStatus(AuthenticationFailedException $failure): ?int
    {
        // A refusal from the identity provider. A 5xx refusal (for example invalid_client) is a
        // fault that an operator must repair.
        if ($failure instanceof ProviderErrorException) {
            $status = $failure->getStatusCode();

            return $status < Response::HTTP_INTERNAL_SERVER_ERROR ? $status : null;
        }

        // The bundle throws a ValidationException with no cause for an invalid state or a missing
        // nonce: a stale callback, an expired session or the back button. A failed token request
        // also gives a ValidationException, but with the error of the request as its cause.
        $cause = $failure->getPrevious();

        return $cause instanceof ValidationException && null === $cause->getPrevious()
            ? Response::HTTP_BAD_REQUEST
            : null;
    }
}

<?php

namespace App\Security;

use ItkDev\OpenIdConnect\Exception\OpenIdConnectExceptionInterface;
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
    ) {
        parent::__construct($providerManager);
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $claims = $this->validateClaims($request);
        } catch (OpenIdConnectExceptionInterface $exception) {
            // Chained, so that onAuthenticationFailure() in the bundle reads the cause and throws
            // AuthenticationFailedException. OidcLoginFailureListener shows it as a page.
            throw new CustomUserMessageAuthenticationException($exception->getMessage(), previous: $exception);
        }

        $user = $this->oidcLogin->login($claims);

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn () => $user));
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
}

<?php

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use ItkDev\OpenIdConnect\Security\OpenIdConfigurationProvider;
use ItkDev\OpenIdConnectBundle\EventSubscriber\AuthenticationAuditSubscriber;
use ItkDev\OpenIdConnectBundle\Security\OpenIdConfigurationProviderManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The OIDC login from end to end. A stub replaces the identity provider, so there are no
 * network calls.
 */
final class OidcLoginFlowTest extends WebTestCase
{
    private const string LOGIN_URL = '/openidconnect/login/default';
    private const string CALLBACK_PATH = '/openid-connect/generic';
    private const string AUTHORIZATION_URL = 'https://idp.example.com/authorize';

    private KernelBrowser $client;

    /**
     * The claims in the ID token from the stub identity provider.
     *
     * @var array<string, mixed>
     */
    private array $claims = ['upn' => 'foobar@example.com', 'roles' => ['ROLE_ADMIN']];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $provider = $this->createStub(OpenIdConfigurationProvider::class);
        $provider->method('generateState')->willReturn('the-state');
        $provider->method('generateNonce')->willReturn('the-nonce');
        $provider->method('generatePkceVerifier')->willReturn('the-verifier');
        $provider->method('getPkceChallenge')->willReturn('the-challenge');
        $provider->method('getAuthorizationUrl')->willReturn(self::AUTHORIZATION_URL);
        $provider->method('getIdToken')->willReturn('the-id-token');
        $provider->method('validateIdToken')->willReturnCallback(fn (): object => (object) $this->claims);

        $manager = $this->createStub(OpenIdConfigurationProviderManager::class);
        $manager->method('getProviderKeys')->willReturn(['default']);
        $manager->method('isCallbackPath')->willReturnCallback(
            static fn (string $path, string $providerKey): bool => self::CALLBACK_PATH === $path && 'default' === $providerKey,
        );
        $manager->method('getProvider')->willReturn($provider);
        $manager->method('isPkceEnabled')->willReturn(true);
        $manager->method('getScopes')->willReturn(['openid']);

        static::getContainer()->set(OpenIdConfigurationProviderManager::class, $manager);
    }

    public function testAnonymousUserIsSentToTheOidcLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects(self::LOGIN_URL);
    }

    public function testTheOidcLoginRedirectsToTheIdentityProvider(): void
    {
        $this->client->request('GET', self::LOGIN_URL);

        self::assertResponseRedirects(self::AUTHORIZATION_URL);
    }

    public function testTheCallbackSignsTheUserInAndCreatesTheUser(): void
    {
        $this->signIn();

        self::assertResponseRedirects('http://localhost/');
        $user = $this->userRepository()->findOneByEmail('foobar@example.com');
        self::assertNotNull($user);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
    }

    public function testBehindTheProxyTheUserIsSentBackToHttps(): void
    {
        $this->client->setServerParameter('HTTP_X_FORWARDED_PROTO', 'https');

        $this->signIn();

        self::assertResponseRedirects('https://localhost/');
    }

    public function testACallbackWithAnInvalidStateIsRejected(): void
    {
        $this->client->request('GET', '/');
        $this->client->request('GET', self::LOGIN_URL);

        $this->client->request('GET', self::CALLBACK_PATH, ['state' => 'another-state', 'code' => 'the-code']);

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertNull($this->userRepository()->findOneByEmail('foobar@example.com'));
    }

    public function testAUserWithNoKnownRoleCanSignInButIsDenied(): void
    {
        $this->claims = ['upn' => 'foobar@example.com', 'roles' => ['ROLE_SUPERHERO']];

        $this->signIn();
        $this->client->request('GET', '/');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testLogoutEndsTheSession(): void
    {
        $this->signIn();

        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/');

        self::assertResponseRedirects(self::LOGIN_URL);
    }

    public function testTheCallbackWithoutALoginIsSentToTheOverview(): void
    {
        $this->client->request('GET', self::CALLBACK_PATH);

        self::assertResponseRedirects('/');
    }

    public function testTheAuthenticationLogIsOn(): void
    {
        self::assertTrue(static::getContainer()->has(AuthenticationAuditSubscriber::class));
    }

    /**
     * Asks for the overview, starts the OIDC login and returns from the identity provider.
     */
    private function signIn(): void
    {
        $this->client->request('GET', '/');
        $this->client->request('GET', self::LOGIN_URL);
        $this->client->request('GET', self::CALLBACK_PATH, ['state' => 'the-state', 'code' => 'the-code']);
    }

    private function userRepository(): UserRepository
    {
        $repository = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        return $repository;
    }
}

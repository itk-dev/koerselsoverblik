<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use ItkDev\OpenIdConnect\Exception\CodeException;
use ItkDev\OpenIdConnect\Security\OpenIdConfigurationProvider;
use ItkDev\OpenIdConnectBundle\Security\OpenIdConfigurationProviderManager;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
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

    /**
     * The error of the token request to the stub identity provider, or null for a valid token.
     */
    private ?\Throwable $tokenError = null;

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
        $provider->method('getIdToken')->willReturnCallback(
            fn (): string => null === $this->tokenError ? 'the-id-token' : throw $this->tokenError,
        );
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
        $errors = $this->logHandler('request');
        $this->client->request('GET', '/');
        $this->client->request('GET', self::LOGIN_URL);

        $this->client->request('GET', self::CALLBACK_PATH, ['state' => 'another-state', 'code' => 'the-code']);

        // A stale callback, for example after the back button, is not a fault of the application.
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSelectorExists('[data-test="login-failed"]');
        self::assertSelectorNotExists('[data-test="login-declined"]');
        self::assertSelectorExists(sprintf('a[href="%s"]', self::LOGIN_URL));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertNull($this->userRepository()->findOneByEmail('foobar@example.com'));
        self::assertSame(['WARNING'], $this->levels($errors));
    }

    public function testALoginThatTheIdentityProviderDeclinesShowsAPage(): void
    {
        $errors = $this->logHandler('request');
        $this->client->request('GET', '/');
        $this->client->request('GET', self::LOGIN_URL);

        $this->client->request('GET', self::CALLBACK_PATH, ['state' => 'the-state', 'error' => 'access_denied']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSelectorExists('[data-test="login-declined"]');
        self::assertSelectorNotExists('[data-test="login-failed"]');
        self::assertSelectorExists(sprintf('a[href="%s"]', self::LOGIN_URL));
        self::assertSame(['WARNING'], $this->levels($errors));
    }

    public function testAnIdentityProviderThatIsNotAvailableIsAnError(): void
    {
        $errors = $this->logHandler('request');
        $this->client->request('GET', '/');
        $this->client->request('GET', self::LOGIN_URL);

        $this->client->request('GET', self::CALLBACK_PATH, ['state' => 'the-state', 'error' => 'temporarily_unavailable']);

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        self::assertSelectorExists('[data-test="login-failed"]');
        self::assertSame(['CRITICAL'], $this->levels($errors));
    }

    public function testAFailedTokenRequestIsAnError(): void
    {
        // For example an expired client secret. This is a fault that an operator must repair.
        $this->tokenError = new CodeException('invalid_client');
        $errors = $this->logHandler('request');

        $this->signIn();

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertSelectorExists('[data-test="login-failed"]');
        self::assertSame(['CRITICAL'], $this->levels($errors));
    }

    public function testALoginWithoutAUpnIsWrittenToTheAuthenticationLog(): void
    {
        // The identity provider approved the login, but the application rejects it.
        $this->claims = ['roles' => ['ROLE_ADMIN']];
        $audit = $this->logHandler('openid_connect_audit');

        $this->signIn();

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $failures = array_values(array_filter(
            $audit->getRecords(),
            static fn (LogRecord $record): bool => 'authentication.login_failed' === $record->message,
        ));
        self::assertCount(1, $failures);
        self::assertSame('The upn claim is missing.', $failures[0]->context['reason']);
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

    /**
     * Asks for the overview, starts the OIDC login and returns from the identity provider.
     */
    private function signIn(): void
    {
        $this->client->request('GET', '/');
        $this->client->request('GET', self::LOGIN_URL);
        $this->client->request('GET', self::CALLBACK_PATH, ['state' => 'the-state', 'code' => 'the-code']);
    }

    /**
     * Adds a handler that keeps the log records of one channel.
     */
    private function logHandler(string $channel): TestHandler
    {
        $logger = static::getContainer()->get('monolog.logger.'.$channel);
        self::assertInstanceOf(Logger::class, $logger);
        $handler = new TestHandler();
        $logger->pushHandler($handler);

        return $handler;
    }

    /**
     * The levels of the records that the error listener writes for an uncaught exception.
     *
     * @return list<string>
     */
    private function levels(TestHandler $handler): array
    {
        return array_values(array_map(
            static fn (LogRecord $record): string => $record->level->getName(),
            array_filter(
                $handler->getRecords(),
                static fn (LogRecord $record): bool => str_starts_with($record->message, 'Uncaught PHP Exception'),
            ),
        ));
    }

    private function userRepository(): UserRepository
    {
        $repository = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        return $repository;
    }
}

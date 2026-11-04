<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the HTTP security headers in "Security" in docs/architecture.md.
 */
final class SecurityHeadersTest extends WebTestCase
{
    use LoginTrait;

    /**
     * The policy directives, except script-src. script-src gets a nonce for each request.
     * There is no style-src, because default-src gives the same value.
     */
    private const array POLICY = [
        'default-src' => ["'self'"],
        'img-src' => ["'self'", 'data:'],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'none'"],
    ];

    public function testPageHasTheSecurityHeaders(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'viewer');

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $this->assertSecurityHeaders($client->getResponse());
    }

    /**
     * A redirect has no content, so nelmio sends no policy and no X-Content-Type-Options.
     */
    public function testRedirectToTheLoginHasTheReferrerPolicy(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
        self::assertResponseHeaderSame('Referrer-Policy', 'same-origin');
    }

    public function testErrorPageHasTheSecurityHeaders(): void
    {
        // In debug mode, Symfony removes the policy from its exception page. Production has no debug mode.
        $client = static::createClient(['debug' => false]);
        $this->loginAs($client, 'viewer');

        $client->request('GET', '/no-such-page');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSecurityHeaders($client->getResponse());
    }

    public function testStrictTransportSecurityIsNotSentOutsideProd(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'viewer');

        $client->request('GET', '/');

        self::assertResponseNotHasHeader('Strict-Transport-Security');
    }

    public function testEveryScriptHasTheNonceFromThePolicy(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'viewer');

        $crawler = $client->request('GET', '/');

        $scriptSources = $this->policy($client)['script-src'] ?? [];
        $scripts = $crawler->filter('script');
        self::assertGreaterThan(0, $scripts->count());
        foreach ($scripts as $script) {
            self::assertInstanceOf(\DOMElement::class, $script);
            $nonce = $script->getAttribute('nonce');
            self::assertNotSame('', $nonce);
            self::assertContains("'nonce-".$nonce."'", $scriptSources);
        }
    }

    private function assertSecurityHeaders(Response $response): void
    {
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('same-origin', $response->headers->get('Referrer-Policy'));

        $policy = $this->parsePolicy((string) $response->headers->get('Content-Security-Policy'));
        foreach (self::POLICY as $directive => $sources) {
            self::assertSame($sources, $policy[$directive] ?? null, $directive);
        }

        // The importmap() output is trusted by its nonce. 'strict-dynamic' lets it load the modules.
        $scriptSources = $policy['script-src'] ?? [];
        self::assertContains("'self'", $scriptSources);
        self::assertContains("'strict-dynamic'", $scriptSources);
        foreach (array_diff($scriptSources, ["'self'", "'strict-dynamic'"]) as $source) {
            self::assertMatchesRegularExpression("/^'nonce-[^']+'$/", $source);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function policy(KernelBrowser $client): array
    {
        return $this->parsePolicy((string) $client->getResponse()->headers->get('Content-Security-Policy'));
    }

    /**
     * @return array<string, list<string>>
     */
    private function parsePolicy(string $header): array
    {
        $policy = [];
        foreach (explode(';', $header) as $directive) {
            $parts = preg_split('/\s+/', trim($directive), -1, \PREG_SPLIT_NO_EMPTY);
            if (false === $parts || [] === $parts) {
                continue;
            }
            $policy[array_shift($parts)] = $parts;
        }

        return $policy;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the "Rettidighed" page: the access, the filter bar and the figures of the base
 * scenario. The tests find the figures by their data-test attributes.
 */
final class PunctualityPageTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use LoginTrait;

    private const string SCENARIOS = __DIR__.'/../fixtures/driving_data';

    public function testAnonymousUserMustSignIn(): void
    {
        $client = static::createClient();

        $client->request('GET', '/punctuality');

        self::assertResponseRedirects('/openidconnect/login/default');
    }

    public function testUserWithNoPermissionRoleIsForbidden(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'user');

        $client->request('GET', '/punctuality');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testViewerSeesThePage(): void
    {
        $crawler = $this->page('viewer');

        self::assertResponseIsSuccessful();
        self::assertSame('Rettidighed', $crawler->filter('h1')->text());
        self::assertSame('Rettidighed', trim($crawler->filter('[data-test="menu-item"][aria-current="page"]')->text()));
        self::assertSame('Rettidighed – Kørselsoverblik', $crawler->filter('title')->text());
    }

    public function testNoTextNamesThePlanningSystem(): void
    {
        $client = $this->client('viewer', importBase: true);
        $client->request('GET', '/punctuality');

        self::assertStringNotContainsString('Optiruns', (string) $client->getResponse()->getContent());
    }

    /**
     * The values of the base scenario, on 5 January 2026. The routes have no supplier.
     */
    public function testBaseScenario(): void
    {
        $crawler = $this->page('viewer', importBase: true);

        self::assertSame('januar 2026', $crawler->filter('[data-test="period"]')->text());
        self::assertSame('100 %', self::value($crawler, 'measure-pickup_per_citizen'));
        self::assertSame('83 %', self::value($crawler, 'measure-drop_off_per_citizen'));
        self::assertSame('100 %', self::value($crawler, 'measure-pickup_per_run'));
        self::assertSame('75 %', self::value($crawler, 'measure-drop_off_per_run'));
        self::assertSame('2 af 4 målinger når målet', $crawler->filter('[data-test="goal-status"]')->text());
        self::assertStringContainsString('7 pct.point under målet', $crawler->filter('[data-test="measure-drop_off_per_citizen"] .tile__note')->text());
        self::assertStringContainsString('Målet er nået', $crawler->filter('[data-test="measure-pickup_per_citizen"] .tile__note')->text());
        // December 2025 has no data, so there is no change.
        self::assertSame('ingen sammenligning', $crawler->filter('[data-test="measure-pickup_per_citizen-change"]')->text());
        self::assertSame('1', self::value($crawler, 'late-runs'));
        self::assertSame('0', self::value($crawler, 'over-30'));
        self::assertSame('4 vognløb', $crawler->filter('[data-test="bands"] .dist__total')->text());

        self::assertSame(
            [['Ukendt', '4', '100 %', '83 %', '100 %', '75 %', '0', '0']],
            self::rows($crawler, 'supplier-row'),
        );
        self::assertSame(
            [['R-01', 'Ukendt', '2', '50 %', '1'], ['BOOKED', 'Ukendt', '1', '100 %', '0'], ['R-02', 'Ukendt', '1', '100 %', '0']],
            self::rows($crawler, 'worst-route-row'),
        );

        // Each chart has a hidden data table.
        self::assertCount(2, $crawler->filter('figure.chart .sr-only table'));
    }

    /**
     * A month with no data shows "–", not 0 %.
     */
    public function testMonthWithNoDataHasNoValues(): void
    {
        $crawler = $this->page('viewer', '?d=2025-12-01', importBase: true);

        self::assertSame('december 2025', $crawler->filter('[data-test="period"]')->text());
        foreach (['pickup_per_citizen', 'drop_off_per_citizen', 'pickup_per_run', 'drop_off_per_run'] as $measure) {
            self::assertSame('–', self::value($crawler, 'measure-'.$measure));
        }
        self::assertSame('0 af 4 målinger når målet', $crawler->filter('[data-test="goal-status"]')->text());
        // No data is not "no late runs", so the count tiles have no value and no tone.
        self::assertSame('–', self::value($crawler, 'late-runs'));
        self::assertSame('–', self::value($crawler, 'over-30'));
        self::assertSame('tile__value', $crawler->filter('[data-test="over-30-value"]')->attr('class'));
        self::assertSame([], self::rows($crawler, 'supplier-row'));
        self::assertSame([], self::rows($crawler, 'worst-route-row'));
        self::assertCount(2, $crawler->filter('[data-test="no-data"]'));
    }

    /**
     * The next month is after the current month, so the current month has no link to it. The
     * link to the current month has no date.
     */
    public function testLinksToThePreviousAndTheNextMonth(): void
    {
        $client = $this->client('viewer');
        $crawler = $client->request('GET', '/punctuality?d=2025-12-01');

        self::assertSame('/punctuality?d=2025-11-01', $crawler->filter('a[data-test="previous-period"]')->attr('href'));
        self::assertSame('/punctuality', $crawler->filter('a[data-test="next-period"]')->attr('href'));

        $crawler = $client->request('GET', '/punctuality');

        self::assertSame('/punctuality?d=2025-12-01', $crawler->filter('a[data-test="previous-period"]')->attr('href'));
        self::assertCount(0, $crawler->filter('a[data-test="next-period"]'));
    }

    /**
     * An invalid month gives the current month, so that a link always works.
     */
    public function testInvalidMonthGivesTheCurrentMonth(): void
    {
        $client = $this->client('viewer');

        self::assertSame('januar 2026', $client->request('GET', '/punctuality?d=abc')->filter('[data-test="period"]')->text());
        self::assertSame('januar 2026', $client->request('GET', '/punctuality?d[]=2025-12-01')->filter('[data-test="period"]')->text());
    }

    /**
     * The Content Security Policy does not allow style attributes, and a script needs the nonce.
     */
    public function testPageFollowsTheContentSecurityPolicy(): void
    {
        $client = $this->client('viewer', importBase: true);
        $crawler = $client->request('GET', '/punctuality');

        self::assertStringNotContainsString('style=', (string) $client->getResponse()->getContent());
        foreach ($crawler->filter('script') as $script) {
            self::assertInstanceOf(\DOMElement::class, $script);
            self::assertNotSame('', $script->getAttribute('nonce'));
        }
    }

    private function page(string $user, string $query = '', bool $importBase = false): Crawler
    {
        return $this->client($user, $importBase)->request('GET', '/punctuality'.$query);
    }

    /**
     * The current date is in January 2026, the month of the base scenario.
     */
    private function client(string $user, bool $importBase = false): KernelBrowser
    {
        self::ensureKernelShutdown();
        self::mockTime('2026-01-20 10:00:00');
        $client = static::createClient();
        $this->loginAs($client, $user);
        if ($importBase) {
            $this->importBase();
        }

        return $client;
    }

    private function importBase(): void
    {
        $scenario = new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml');
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);
        new ExtractWriter()->write($path, $provider->default(), $scenario->rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $importer->import($path, ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 31), $provider->default(), null, 'base.xlsx');
        unlink($path);
    }

    private static function value(Crawler $crawler, string $test): string
    {
        return $crawler->filter(\sprintf('[data-test="%s-value"]', $test))->text();
    }

    /**
     * @return list<list<string>>
     */
    private static function rows(Crawler $crawler, string $test): array
    {
        return $crawler->filter(\sprintf('[data-test="%s"]', $test))->each(
            static fn (Crawler $row): array => $row->filter('th, td')->each(static fn (Crawler $cell): string => $cell->text()),
        );
    }
}

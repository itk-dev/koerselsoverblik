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

/**
 * Checks the overview: the month filter and the trips and the citizens of the base scenario.
 * OverviewAccessTest checks the access. The tests find the figures by their data-test attributes.
 */
final class OverviewPageTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use LoginTrait;

    private const string SCENARIOS = __DIR__.'/../fixtures/driving_data';

    public function testViewerSeesThePage(): void
    {
        $crawler = $this->page('viewer');

        self::assertResponseIsSuccessful();
        self::assertSame('Bliver borgerkørslen bedre eller værre?', $crawler->filter('h1')->text());
        self::assertSame('Overblik', trim($crawler->filter('[data-test="menu-item"][aria-current="page"]')->text()));
        self::assertSame('Overblik – Kørselsoverblik', $crawler->filter('title')->text());
        self::assertSame('januar 2026 sammenlignet med december 2025.', $crawler->filter('.page-head__lede')->text());
    }

    /**
     * The values of the base scenario, on 5 January 2026.
     */
    public function testBaseScenario(): void
    {
        $crawler = $this->page('viewer', importBase: true);

        self::assertSame('januar 2026', $crawler->filter('[data-test="month"]')->text());
        self::assertSame('7', self::value($crawler, 'trips'));
        self::assertSame('5 gennemført · 1 aflyst · 1 uden registrering', $crawler->filter('[data-test="trips"] .tile__note')->text());
        self::assertSame('6', self::value($crawler, 'citizens'));
        self::assertSame('Forskellige borgere, uanset antal ture', $crawler->filter('[data-test="citizens"] .tile__note')->text());
        // December 2025 has no data, so there is no change.
        self::assertSame('ingen sammenligning', $crawler->filter('[data-test="trips-change"]')->text());
        self::assertSame('ingen sammenligning', $crawler->filter('[data-test="citizens-change"]')->text());
        self::assertCount(1, $crawler->filter('#help-trips'));
        self::assertCount(1, $crawler->filter('#help-citizens'));
    }

    /**
     * The overview shows the same punctuality as the punctuality page (PunctualityPageTest).
     */
    public function testBaseScenarioPunctuality(): void
    {
        $crawler = $this->page('viewer', importBase: true);

        self::assertSame('75 %', self::value($crawler, 'punctuality'));
        self::assertSame('Sidste afsætning pr. vognløb · Mål 90 %', $crawler->filter('[data-test="punctuality"] .tile__note')->text());
        self::assertCount(1, $crawler->filter('#help-punctuality'));
        self::assertSame('2 af 4 målinger når målet', $crawler->filter('[data-test="goal-status"]')->text());
        self::assertSame('100 %', self::value($crawler, 'measure-pickup_per_citizen'));
        self::assertSame('83 %', self::value($crawler, 'measure-drop_off_per_citizen'));
        self::assertSame('100 %', self::value($crawler, 'measure-pickup_per_run'));
        self::assertSame('75 %', self::value($crawler, 'measure-drop_off_per_run'));
        self::assertSame('Rettidighed mod servicemålet', $crawler->filter('[data-test="punctuality-trend"] .panel__title')->text());
        // The chart has a hidden data table.
        self::assertCount(1, $crawler->filter('figure.chart .sr-only table'));
    }

    /**
     * December 2025 has no trips. No trips is no data, so the tiles show "–", not 0, and the trips
     * have no note.
     */
    public function testMonthWithNoDataHasNoValues(): void
    {
        $crawler = $this->page('viewer', '?month=2025-12', importBase: true);

        self::assertSame('december 2025', $crawler->filter('[data-test="month"]')->text());
        self::assertSame('–', self::value($crawler, 'trips'));
        self::assertSame('–', self::value($crawler, 'citizens'));
        self::assertCount(0, $crawler->filter('[data-test="trips"] .tile__note'));
        self::assertSame('–', self::value($crawler, 'punctuality'));
        foreach (['pickup_per_citizen', 'drop_off_per_citizen', 'pickup_per_run', 'drop_off_per_run'] as $measure) {
            self::assertSame('–', self::value($crawler, 'measure-'.$measure));
        }
    }

    /**
     * The next month is after the current month, so the current month has no link to it.
     */
    public function testLinksToThePreviousAndTheNextMonth(): void
    {
        $client = $this->client('viewer');
        $crawler = $client->request('GET', '/?month=2025-12');

        self::assertSame('/?month=2025-11', $crawler->filter('a[data-test="previous-month"]')->attr('href'));
        self::assertSame('/?month=2026-01', $crawler->filter('a[data-test="next-month"]')->attr('href'));

        $crawler = $client->request('GET', '/');

        self::assertSame('/?month=2025-12', $crawler->filter('a[data-test="previous-month"]')->attr('href'));
        self::assertCount(0, $crawler->filter('a[data-test="next-month"]'));
    }

    /**
     * An invalid month gives the current month, so that a link always works.
     */
    public function testInvalidMonthGivesTheCurrentMonth(): void
    {
        $client = $this->client('viewer');

        self::assertSame('januar 2026', $client->request('GET', '/?month=abc')->filter('[data-test="month"]')->text());
    }

    /**
     * The Content Security Policy does not allow style attributes, and a script needs the nonce.
     */
    public function testPageFollowsTheContentSecurityPolicy(): void
    {
        $client = $this->client('viewer', importBase: true);
        $crawler = $client->request('GET', '/');

        self::assertStringNotContainsString('style=', (string) $client->getResponse()->getContent());
        foreach ($crawler->filter('script') as $script) {
            self::assertInstanceOf(\DOMElement::class, $script);
            self::assertNotSame('', $script->getAttribute('nonce'));
        }
    }

    private function page(string $user, string $query = '', bool $importBase = false): Crawler
    {
        return $this->client($user, $importBase)->request('GET', '/'.$query);
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
}

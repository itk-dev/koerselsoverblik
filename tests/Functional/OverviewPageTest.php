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
        self::assertSame('5 gennemført · 1 aflyst', $crawler->filter('[data-test="trips"] .tile__note')->text());
        self::assertSame('6', self::value($crawler, 'citizens'));
        self::assertSame('Forskellige borgere, uanset antal ture', $crawler->filter('[data-test="citizens"] .tile__note')->text());
        // December 2025 has no data, so there is no change.
        self::assertSame('ingen sammenligning', $crawler->filter('[data-test="trips-change"]')->text());
        self::assertSame('ingen sammenligning', $crawler->filter('[data-test="citizens-change"]')->text());
        self::assertCount(1, $crawler->filter('#help-trips'));
        self::assertCount(1, $crawler->filter('#help-citizens'));
    }

    /**
     * The routes have no supplier: 7 trips, 3 of 4 last drop-offs on time, 2 of 4 solo runs and
     * 1 of 7 trips cancelled.
     */
    public function testBaseScenarioSupplierTable(): void
    {
        $crawler = $this->page('viewer', importBase: true);

        self::assertSame(
            [['Ukendt', '7', '75 %', '50 %', '14 %']],
            $crawler->filter('[data-test="overview-supplier-row"]')->each(
                static fn (Crawler $row): array => $row->filter('th, td')->each(static fn (Crawler $cell): string => $cell->text()),
            ),
        );
    }

    /**
     * R-01 run 2 and BOOKED have at most one citizen in the car: 2 of 4 runs.
     */
    public function testBaseScenarioRealSoloRuns(): void
    {
        $crawler = $this->page('viewer', importBase: true);

        self::assertSame('50 %', self::value($crawler, 'real-solo'));
        self::assertSame('2 vognløb med højst én borger', $crawler->filter('[data-test="real-solo"] .tile__note')->text());
        self::assertSame('ingen sammenligning', $crawler->filter('[data-test="real-solo-change"]')->text());
        self::assertCount(1, $crawler->filter('#help-real-solo'));
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
     * December 2025 has no trips. No trips is no data, so the tiles show "–", not 0.
     */
    public function testMonthWithNoDataHasNoValues(): void
    {
        $crawler = $this->page('viewer', '?month=2025-12', importBase: true);

        self::assertSame('december 2025', $crawler->filter('[data-test="month"]')->text());
        self::assertSame('–', self::value($crawler, 'trips'));
        self::assertSame('–', self::value($crawler, 'citizens'));
        self::assertSame('0 gennemført · 0 aflyst', $crawler->filter('[data-test="trips"] .tile__note')->text());
        self::assertSame('–', self::value($crawler, 'punctuality'));
        self::assertSame('–', self::value($crawler, 'real-solo'));
        self::assertCount(0, $crawler->filter('[data-test="overview-supplier-row"]'));
        self::assertCount(1, $crawler->filter('[data-test="overview-suppliers"] [data-test="no-data"]'));
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

    public function testNoTextNamesThePlanningSystem(): void
    {
        $client = $this->client('viewer', importBase: true);
        $client->request('GET', '/');

        self::assertStringNotContainsString('Optiruns', (string) $client->getResponse()->getContent());
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

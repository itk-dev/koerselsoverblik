<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Checks the overview: the filter bar and the trips and the citizens of the base scenario.
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
        self::assertSame('1.–20. januar 2026 (til og med i dag) sammenlignet med 1.–20. december 2025.', $crawler->filter('.page-head__lede')->text());
    }

    /**
     * The values of the base scenario, on 5 January 2026.
     */
    public function testBaseScenario(): void
    {
        $crawler = $this->page('viewer', importBase: true);

        self::assertSame('januar 2026', $crawler->filter('[data-test="period"]')->text());
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
     * R-01 run 2 and BOOKED have exactly one citizen in the car: 2 of 4 runs.
     */
    public function testBaseScenarioRealSoloRuns(): void
    {
        $crawler = $this->page('viewer', importBase: true);

        self::assertSame('50 %', self::value($crawler, 'real-solo'));
        self::assertSame('2 vognløb med én borger ad gangen', $crawler->filter('[data-test="real-solo"] .tile__note')->text());
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
        self::assertSame('Afsætning pr. vognløb · Mål 90 %', $crawler->filter('[data-test="punctuality"] .tile__note')->text());
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
     * and the real solo runs have no note. The chart shows the 12 months with no values.
     */
    public function testMonthWithNoDataHasNoValues(): void
    {
        $crawler = $this->page('viewer', '?d=2025-12-01', importBase: true);

        self::assertSame('december 2025', $crawler->filter('[data-test="period"]')->text());
        self::assertSame('–', self::value($crawler, 'trips'));
        self::assertSame('–', self::value($crawler, 'citizens'));
        self::assertCount(0, $crawler->filter('[data-test="trips"] .tile__note'));
        self::assertSame('–', self::value($crawler, 'punctuality'));
        self::assertSame('–', self::value($crawler, 'real-solo'));
        self::assertCount(0, $crawler->filter('[data-test="real-solo"] .tile__note'));
        self::assertCount(0, $crawler->filter('[data-test="overview-supplier-row"]'));
        self::assertCount(1, $crawler->filter('[data-test="overview-suppliers"] [data-test="no-data"]'));
        foreach (['pickup_per_citizen', 'drop_off_per_citizen', 'pickup_per_run', 'drop_off_per_run'] as $measure) {
            self::assertSame('–', self::value($crawler, 'measure-'.$measure));
        }
        self::assertSame('0 af 4 målinger når målet', $crawler->filter('[data-test="goal-status"]')->text());
        $rows = $crawler->filter('[data-test="punctuality-trend"] .sr-only tbody tr');
        self::assertCount(12, $rows);
        self::assertSame(['–', '–', '–', '–'], $rows->last()->filter('td')->each(static fn (Crawler $cell): string => $cell->text()));
    }

    /**
     * The next month is after the current month, so the current month has no link to it. The
     * link to the current month has no date.
     */
    public function testLinksToThePreviousAndTheNextMonth(): void
    {
        $client = $this->client('viewer');
        $crawler = $client->request('GET', '/?d=2025-12-01');

        self::assertSame('/?d=2025-11-01', $crawler->filter('a[data-test="previous-period"]')->attr('href'));
        self::assertSame('/', $crawler->filter('a[data-test="next-period"]')->attr('href'));

        $crawler = $client->request('GET', '/');

        self::assertSame('/?d=2025-12-01', $crawler->filter('a[data-test="previous-period"]')->attr('href'));
        self::assertCount(0, $crawler->filter('a[data-test="next-period"]'));
    }

    /**
     * Week 2 of 2026 has the base scenario. It is compared with week 2 of 2025. Each link of the
     * filter bar changes one filter and keeps the others.
     */
    public function testFilterBarKeepsTheOtherFilters(): void
    {
        $crawler = $this->page('viewer', '?p=week&d=2026-01-07&c=last_year', importBase: true);

        self::assertSame('Uge 2 · 5. jan–11. jan 2026', $crawler->filter('[data-test="period"]')->text());
        self::assertSame('uge 2 · 5. jan–11. jan 2026 sammenlignet med uge 2 · 6. jan–12. jan 2025.', $crawler->filter('[data-test="lede"]')->text());
        self::assertSame('7', self::value($crawler, 'trips'));
        // January 2026 is the current month, so its link has no date.
        self::assertSame('/?c=last_year', $crawler->filter('[data-test="period-month"]')->attr('href'));
        // The period switch is a list of links. The link of the selected type is the current one.
        self::assertCount(1, $crawler->filter('nav [data-test="period-week"]'));
        self::assertSame('true', $crawler->filter('[data-test="period-week"]')->attr('aria-current'));
        self::assertNull($crawler->filter('[data-test="period-month"]')->attr('aria-current'));
        self::assertNull($crawler->filter('[data-test="period-week"]')->attr('role'));
        self::assertSame('/?p=week&d=2025-12-29&c=last_year', $crawler->filter('[data-test="previous-period"]')->attr('href'));
        self::assertSame('last_year', $crawler->filter('[data-test="comparison"] option[selected]')->attr('value'));
        // The form sends the other filters with the comparison.
        self::assertSame(
            ['p' => 'week', 'd' => '2026-01-05'],
            array_combine(
                $crawler->filter('[data-filter-form] input[type="hidden"]')->each(static fn (Crawler $input): string => (string) $input->attr('name')),
                $crawler->filter('[data-filter-form] input[type="hidden"]')->each(static fn (Crawler $input): string => (string) $input->attr('value')),
            ),
        );
    }

    /**
     * The sub-agreement R (R-01 and R-02) has the supplier. BOOKED has no supplier. The tiles
     * show the supplier only. The supplier table always shows all suppliers.
     */
    public function testSupplierFilter(): void
    {
        $client = $this->client('viewer', importBase: true);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $supplier = new Supplier()->setName('Vognmand A');
        $entityManager->persist($supplier);
        $entityManager->getRepository(SubAgreement::class)->findOneBy(['reference' => 'R'])?->setSupplier($supplier);
        $entityManager->flush();

        $crawler = $client->request('GET', '/?s='.$supplier->getId());

        self::assertSame('6', self::value($crawler, 'trips'));
        self::assertSame((string) $supplier->getId(), $crawler->filter('[data-test="supplier"] option[selected]')->attr('value'));
        self::assertSame(
            [['Vognmand A', '6', '67 %', '33 %', '17 %'], ['Ukendt', '1', '100 %', '100 %', '0 %']],
            $crawler->filter('[data-test="overview-supplier-row"]')->each(
                static fn (Crawler $row): array => $row->filter('th, td')->each(static fn (Crawler $cell): string => $cell->text()),
            ),
        );
        // A link of the filter bar keeps the supplier.
        self::assertSame('/?p=week&s='.$supplier->getId(), $crawler->filter('[data-test="period-week"]')->attr('href'));
        // A supplier that does not exist gives all suppliers.
        self::assertSame('7', self::value($client->request('GET', '/?s=999999'), 'trips'));
    }

    /**
     * The quarter has not ended, so the previous quarter is cut to the same number of days.
     */
    public function testQuarterThatHasNotEnded(): void
    {
        $crawler = $this->page('viewer', '?p=quarter');

        self::assertSame('1. kvartal 2026', $crawler->filter('[data-test="period"]')->text());
        self::assertSame('1.–20. januar 2026 (til og med i dag) sammenlignet med 1.–20. oktober 2025.', $crawler->filter('[data-test="lede"]')->text());
    }

    /**
     * An invalid month gives the current month, so that a link always works.
     */
    public function testInvalidMonthGivesTheCurrentMonth(): void
    {
        $client = $this->client('viewer');

        self::assertSame('januar 2026', $client->request('GET', '/?d=abc')->filter('[data-test="period"]')->text());
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

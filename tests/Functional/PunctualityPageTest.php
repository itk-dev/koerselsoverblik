<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\DrivingData\ExtractRow;
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
        // The note gives the share of the measured runs. The change is of the share.
        self::assertSame('25 % af 4 vognløb', $crawler->filter('[data-test="late-runs"] .tile__note')->text());
        self::assertSame('0 % af 4 vognløb', $crawler->filter('[data-test="over-30"] .tile__note')->text());
        self::assertSame('ingen sammenligning', $crawler->filter('[data-test="late-runs-change"]')->text());
        // The chart of the delays shows the share of the measured runs, and the bar title keeps the count.
        self::assertContains('jan · 5–15 min: 25 % (1 vognløb)', $crawler->filter('#severity title')->each(static fn (Crawler $title): string => $title->text()));
        // The hidden data table gives the share and the count of each band, as the bar titles do.
        self::assertSame(
            ['jan', '25 % (1)', '0 % (0)', '0 % (0)'],
            $crawler->filter('#severity table tbody tr')->last()->filter('th, td')->each(static fn (Crawler $cell): string => $cell->text()),
        );
        self::assertSame('4 vognløb', $crawler->filter('[data-test="bands"] .dist__total')->text());
        // Each count tile has a "?". The late runs use the tolerance, and the runs over 30 minutes a fixed limit.
        self::assertStringContainsString('mere end 5 minutter', $crawler->filter('[data-test="late-runs"] #help-late-runs')->text());
        self::assertStringContainsString('mere end 30 minutter', $crawler->filter('[data-test="over-30"] #help-over-30')->text());

        // The delay columns have the labels of the split bar.
        self::assertSame(
            ['5–15 min. forsinket', '15–30 min. forsinket', 'Over 30 min. forsinket'],
            \array_slice($crawler->filter('[data-test="suppliers"] thead th')->each(static fn (Crawler $cell): string => $cell->text()), -3),
        );
        // The runs are the runs on time and the three delay columns: 4 = 3 + 1 + 0 + 0.
        self::assertSame(
            [['Ukendt', '4', '100 %', '83 %', '100 %', '75 %', '1', '0', '0']],
            self::rows($crawler, 'supplier-row'),
        );
        self::assertStringContainsString('sidste afsætning', $crawler->filter('[data-test="suppliers-note"]')->text());
        // The routes with the most late runs are first. The punctuality is the last column, because it comes from the counts.
        self::assertStringContainsString('De ti vognløb med flest forsinkede sidste afsætninger', $crawler->filter('[data-test="worst-routes"]')->text());
        self::assertSame(
            [['R-01', 'Ukendt', '2', '1', '50 %']],
            self::rows($crawler, 'worst-route-row'),
        );
        // The location, the measured stops, the late stops and the punctuality, the worst first.
        // The test environment has a minimum of 2 stops, so "Skole C" with 1 stop is left out.
        self::assertSame(
            [['Skole A', '3', '1', '67 %'], ['Skole B', '3', '0', '100 %']],
            self::rows($crawler, 'worst-location-row'),
        );
        self::assertSame('1 lokation med færre end 2 målte stop indgår ikke.', $crawler->filter('[data-test="locations-left-out"]')->text());
        self::assertStringContainsString('mindst 2 målte', $crawler->filter('#help-locations')->text());
        self::assertStringContainsString('t-danger', (string) $crawler->filter('[data-test="worst-location-row"]')->first()->filter('td')->last()->attr('class'));
        // The locations are after the suppliers and before the worst routes.
        self::assertSame(
            ['supplier-row', 'worst-location-row', 'worst-route-row'],
            array_values(array_unique($crawler->filter('[data-test="supplier-row"], [data-test="worst-location-row"], [data-test="worst-route-row"]')->each(
                static fn (Crawler $row): string => (string) $row->attr('data-test'),
            ))),
        );

        // The chart of the delays is in a panel of half the width, so its viewBox has half the width.
        // Then its axis labels have the same size on screen as those of the trend chart.
        self::assertSame('0 0 840 280', $crawler->filter('#trend svg')->attr('viewBox'));
        self::assertSame('0 0 420 280', $crawler->filter('#severity svg')->attr('viewBox'));

        // Each chart has a hidden data table.
        self::assertCount(2, $crawler->filter('figure.chart .sr-only table'));

        // No sub-agreement is ignored, so the page has no note about it.
        self::assertCount(0, $crawler->filter('[data-test="ignored-note"]'));
    }

    /**
     * The figures do not include the routes of an ignored sub-agreement, so the page names the
     * ignored sub-agreements, sorted by the reference.
     */
    public function testPageNamesTheIgnoredSubAgreements(): void
    {
        $client = $this->client('viewer');
        $this->subAgreement('TEST', true);
        $this->subAgreement('OTHER', false);

        self::assertSame('1 delaftale er ignoreret i tallene: TEST', $client->request('GET', '/punctuality')->filter('[data-test="ignored-note"]')->text());

        $this->subAgreement('BOOKED', true);

        self::assertSame('2 delaftaler er ignoreret i tallene: BOOKED, TEST', $client->request('GET', '/punctuality')->filter('[data-test="ignored-note"]')->text());
    }

    /**
     * The figures of a supplier only have the routes of its sub-agreements. Thus with a supplier
     * filter, the note names only the ignored sub-agreements of that supplier.
     */
    public function testNoteWithASupplierFilterNamesOnlyThatSupplier(): void
    {
        $client = $this->client('viewer');
        $supplier = $this->supplier('Vognmand A');
        $other = $this->supplier('Vognmand B');
        $this->subAgreement('OTHER', true, $other);
        $this->subAgreement('BOOKED', true);

        self::assertCount(0, $client->request('GET', '/punctuality?s='.$supplier->getId())->filter('[data-test="ignored-note"]'));

        $this->subAgreement('TEST', true, $supplier);

        self::assertSame('1 delaftale er ignoreret i tallene: TEST', $client->request('GET', '/punctuality?s='.$supplier->getId())->filter('[data-test="ignored-note"]')->text());
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
        // No month has measured runs, so the chart of the delays has no bar.
        self::assertCount(0, $crawler->filter('#severity title'));
        self::assertSame([], self::rows($crawler, 'supplier-row'));
        // With no columns, the note about the delay columns is not shown.
        self::assertCount(0, $crawler->filter('[data-test="suppliers-note"]'));
        self::assertSame([], self::rows($crawler, 'worst-route-row'));
        self::assertSame([], self::rows($crawler, 'worst-location-row'));
        self::assertSame(['Ingen data i perioden.'], $crawler->filter('[data-test="worst-locations"] [data-test="no-data"]')->each(static fn (Crawler $text): string => $text->text()));
        self::assertCount(0, $crawler->filter('[data-test="locations-left-out"]'));
        self::assertCount(3, $crawler->filter('[data-test="no-data"]'));
    }

    /**
     * The route BOOKED has one stop at "Skole C". The test environment has a minimum of 2 stops,
     * so the panel names the minimum, and not "no data".
     */
    public function testLocationsWithTooFewStopsAreNotRanked(): void
    {
        $client = $this->client('viewer');
        $this->importBase('BOOKED');

        $crawler = $client->request('GET', '/punctuality');

        self::assertSame([], self::rows($crawler, 'worst-location-row'));
        self::assertSame('Ingen lokation har mindst 2 målte stop i perioden.', $crawler->filter('[data-test="worst-locations"] [data-test="no-data"]')->text());
        self::assertSame('1 lokation med færre end 2 målte stop indgår ikke.', $crawler->filter('[data-test="locations-left-out"]')->text());
    }

    /**
     * The route BOOKED has one run, and it is on time. The period has data, so the panel of the
     * worst routes tells that no run is late, and not "no data".
     */
    public function testRoutesWithNoLateRunAreNotListed(): void
    {
        $client = $this->client('viewer');
        $this->importBase('BOOKED');

        $crawler = $client->request('GET', '/punctuality');

        self::assertSame([], self::rows($crawler, 'worst-route-row'));
        self::assertSame('Ingen vognløb havde en forsinket sidste afsætning i perioden.', $crawler->filter('[data-test="worst-routes"] [data-test="no-data"]')->text());
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

        foreach (['?d=abc', '?d[]=2025-12-01'] as $query) {
            $client->request('GET', '/punctuality'.$query);
            self::assertResponseRedirects('/punctuality');
            self::assertSame('januar 2026', $client->followRedirect()->filter('[data-test="period"]')->text());
        }
    }

    /**
     * The tiles show the whole week, but the sparklines and the charts show months.
     */
    public function testPeriodThatIsNotAMonthHasANoteOfTheMonths(): void
    {
        $client = $this->client('viewer');

        self::assertSame('Kurverne viser hele måneder.', $client->request('GET', '/punctuality?p=week')->filter('[data-test="months-note"]')->text());
        self::assertCount(0, $client->request('GET', '/punctuality')->filter('[data-test="months-note"]'));
    }

    /**
     * Each view has one URL. The form sends the default values, so the page redirects them.
     */
    public function testQueryThatIsNotCanonicalRedirects(): void
    {
        $client = $this->client('viewer');
        $client->request('GET', '/punctuality?c=last_year&s=');

        self::assertResponseRedirects('/punctuality?c=last_year');
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

    /**
     * @param ?string $route the one route of the scenario to import, or null for all routes
     */
    private function importBase(?string $route = null): void
    {
        $rows = new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml')->rows;
        if (null !== $route) {
            $rows = array_values(array_filter($rows, static fn (ExtractRow $row): bool => $route === $row->values['route']));
        }
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);
        new ExtractWriter()->write($path, $provider->default(), $rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $importer->import($path, ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 31), $provider->default(), null, 'base.xlsx');
        unlink($path);
    }

    private function subAgreement(string $reference, bool $ignored, ?Supplier $supplier = null): void
    {
        $this->persist(new SubAgreement()->setReference($reference)->setIgnored($ignored)->setSupplier($supplier));
    }

    private function supplier(string $name): Supplier
    {
        $supplier = new Supplier()->setName($name);
        $this->persist($supplier);

        return $supplier;
    }

    private function persist(object $entity): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->persist($entity);
        $entityManager->flush();
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

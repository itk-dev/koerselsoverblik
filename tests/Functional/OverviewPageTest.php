<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\RouteDayTotals;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Import\DrivingDataImporter;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

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
     * December 2025 has no data, so the conclusion has no comparison and no sentence.
     */
    public function testConclusionWithNoComparison(): void
    {
        $crawler = $this->page('viewer', importBase: true);

        self::assertSame('Ingen sammenligning for denne periode.', $crawler->filter('[data-test="conclusion-head"]')->text());
        self::assertSame('Vælg en anden periode eller et andet sammenligningsgrundlag for at se en retning.', $crawler->filter('[data-test="conclusion-text"]')->text());
    }

    /**
     * The default conclusion uses the punctuality: 75 % in January against 67 % in December.
     */
    public function testConclusionOfThePunctuality(): void
    {
        $crawler = $this->clientWithAComparison()->request('GET', '/');

        self::assertSame('Servicen er blevet bedre.', $crawler->filter('[data-test="conclusion-head"]')->text());
        self::assertSame(
            'I 1.–20. januar 2026 (til og med i dag) mod 1.–20. december 2025: 75 % af vognløbene blev afsluttet til tiden mod 67 %.',
            $crawler->filter('[data-test="conclusion-text"]')->text(),
        );
        self::assertStringContainsString('itkaarhus-callout--success', (string) $crawler->filter('[data-test="conclusion"]')->attr('class'));
        self::assertSame('Ændringer under 1 pct.point tæller som uændret.', $crawler->filter('[data-test="conclusion-threshold"]')->text());
    }

    /**
     * The punctuality and the cancelled trips are better, but the real solo runs are worse.
     */
    public function testConclusionOfAllMeasures(): void
    {
        $crawler = $this->clientWithAComparison()->request('GET', '/?m=punctuality,solo,cancelled&t=0');

        self::assertSame('Blandet billede.', $crawler->filter('[data-test="conclusion-head"]')->text());
        self::assertSame(
            'I 1.–20. januar 2026 (til og med i dag) mod 1.–20. december 2025: 75 % af vognløbene blev afsluttet til tiden mod 67 %, 50 % af vognløbene kørte med én borger mod 33 %, og 14 % af turene blev aflyst mod 17 %.',
            $crawler->filter('[data-test="conclusion-text"]')->text(),
        );
        self::assertStringContainsString('itkaarhus-callout--info', (string) $crawler->filter('[data-test="conclusion"]')->attr('class'));
        self::assertSame('Enhver ændring tæller med.', $crawler->filter('[data-test="conclusion-threshold"]')->text());
        // The value of the selected period is in bold.
        self::assertSame(['75 %', '50 %', '14 %'], $crawler->filter('[data-test="conclusion-text"] strong')->each(static fn (Crawler $value): string => $value->text()));
    }

    /**
     * The form of "Tilpas konklusion" shows the settings, and sends the filters with them.
     */
    public function testConclusionSettings(): void
    {
        $crawler = $this->page('viewer', '?p=week&d=2026-01-05&c=last_year&m=solo,cancelled&t=0.5');
        $form = $crawler->filter('[data-test="conclusion-settings"] form');

        self::assertSame('Tilpas konklusion', $crawler->filter('[data-test="conclusion-settings"] summary [data-test="conclusion-settings-label"]')->text());
        self::assertSame(
            ['p' => 'week', 'd' => '2026-01-05', 'c' => 'last_year'],
            array_combine(
                $form->filter('input[type="hidden"]')->each(static fn (Crawler $input): string => (string) $input->attr('name')),
                $form->filter('input[type="hidden"]')->each(static fn (Crawler $input): string => (string) $input->attr('value')),
            ),
        );
        self::assertSame(
            ['Rettidighed' => false, 'Reelt solo' => true, 'Aflyste ture' => true],
            array_combine(
                $form->filter('label.check')->each(static fn (Crawler $label): string => $label->text()),
                $form->filter('input[name="m[]"]')->each(static fn (Crawler $input): bool => null !== $input->attr('checked')),
            ),
        );
        self::assertSame(['Enhver ændring', 'Mindst 0,5 pct.point', 'Mindst 1 pct.point', 'Mindst 2 pct.point', 'Mindst 5 pct.point'], $form->filter('select[name="t"] option')->each(static fn (Crawler $option): string => $option->text()));
        self::assertSame('0.5', $form->filter('select[name="t"] option[selected]')->attr('value'));
        self::assertSame('Ændringer under 0,5 pct.point tæller som uændret.', $crawler->filter('[data-test="conclusion-threshold"]')->text());
    }

    /**
     * The links and the form of the filter bar keep the settings of the conclusion.
     */
    public function testFilterBarKeepsTheConclusionSettings(): void
    {
        $crawler = $this->page('viewer', '?m=solo&t=0');

        self::assertSame('/?p=week&m=solo&t=0', $crawler->filter('[data-test="period-week"]')->attr('href'));
        self::assertSame(
            ['m' => 'solo', 't' => '0'],
            array_combine(
                $crawler->filter('[data-filter-form] input[type="hidden"]')->each(static fn (Crawler $input): string => (string) $input->attr('name')),
                $crawler->filter('[data-filter-form] input[type="hidden"]')->each(static fn (Crawler $input): string => (string) $input->attr('value')),
            ),
        );
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
        $crawler = $this->page('viewer', '?p=week&d=2026-01-05&c=last_year', importBase: true);

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
        $client->request('GET', '/?s=999999');
        self::assertResponseRedirects('/');
        self::assertSame('7', self::value($client->followRedirect(), 'trips'));
    }

    /**
     * The quarter has not ended, so the previous quarter is cut to the same number of days.
     */
    public function testQuarterThatHasNotEnded(): void
    {
        $crawler = $this->page('viewer', '?p=quarter');

        self::assertSame('1. kvartal 2026', $crawler->filter('[data-test="period"]')->text());
        self::assertSame('1.–20. januar 2026 (til og med i dag) sammenlignet med 1.–20. oktober 2025.', $crawler->filter('[data-test="lede"]')->text());
        // The tiles show the whole quarter, but the sparklines show months.
        self::assertSame('Kurverne viser hele måneder.', $crawler->filter('[data-test="months-note"]')->text());
    }

    /**
     * For a month, the last point of a sparkline is the month of the tile, so the page has no note.
     */
    public function testMonthHasNoNoteOfTheMonths(): void
    {
        self::assertCount(0, $this->page('viewer')->filter('[data-test="months-note"]'));
    }

    /**
     * An invalid month gives the current month, so that a link always works.
     */
    public function testInvalidMonthGivesTheCurrentMonth(): void
    {
        $client = $this->client('viewer');
        $client->request('GET', '/?d=abc');

        self::assertResponseRedirects('/');
        self::assertSame('januar 2026', $client->followRedirect()->filter('[data-test="period"]')->text());
    }

    /**
     * Each view has one URL. The form sends the default values, and a date can be any date of
     * the period. The page redirects such a query to the query of the links.
     */
    #[DataProvider('nonCanonicalQueries')]
    public function testQueryThatIsNotCanonicalRedirects(string $query, string $location): void
    {
        $client = $this->client('viewer');
        $client->request('GET', '/'.$query);

        self::assertResponseRedirects($location);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonCanonicalQueries(): iterable
    {
        yield 'defaults from the form' => ['?c=previous&s=', '/'];
        yield 'date in the period' => ['?p=week&d=2026-01-07', '/?p=week&d=2026-01-05'];
        yield 'date of the current period' => ['?d=2026-01-20', '/'];
        yield 'unknown parameter' => ['?month=2025-06', '/'];
        // The checkboxes of the form send a list. The link has a list with commas.
        yield 'measures from the form' => ['?m[]=solo&m[]=punctuality&t=2', '/?m=punctuality,solo&t=2'];
        yield 'default settings of the conclusion' => ['?m=punctuality&t=1', '/'];
        yield 'unknown measure' => ['?m=complaints', '/'];
        yield 'threshold that is not an option' => ['?t=3', '/'];
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

    /**
     * Totals that were built with another tolerance give a page that names the command, not the
     * default error page.
     */
    public function testTotalsWithAnotherToleranceShowTheCommand(): void
    {
        $client = $this->client('viewer', importBase: true);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('UPDATE '.RouteDayTotals::class.' totals SET totals.toleranceMinutes = 10')->execute();

        $crawler = $client->request('GET', '/');

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        self::assertStringContainsString('app:driving-data:rebuild-totals', $crawler->filter('[data-test="other-tolerance"]')->text());
        self::assertStringContainsString('ændret til 5 minutter', $crawler->filter('[data-test="other-tolerance"]')->text());
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

    /**
     * @param int     $daysBefore the number of days to move the dates back, for data in a comparison period
     * @param ?string $leaveOut   a route to leave out, so that the figures are not the same
     */
    private function importBase(int $daysBefore = 0, ?string $leaveOut = null): void
    {
        $rows = [];
        foreach (new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml')->rows as $row) {
            if ($leaveOut === $row->values['route']) {
                continue;
            }
            $values = $row->values;
            foreach (['service_date', 'actual_time'] as $field) {
                if ($values[$field] instanceof \DateTimeImmutable) {
                    $values[$field] = $values[$field]->modify(\sprintf('-%d days', $daysBefore));
                }
            }
            // A row id is stored on one date only, so the moved rows need their own ids.
            $rowId = 0 === $daysBefore ? $row->rowId : \sprintf('before-%d-%d', $daysBefore, \count($rows));
            $rows[] = new ExtractRow($values, $rowId, $row->raw, $row->kept);
        }
        $date = new \DateTimeImmutable('2026-01-05')->modify(\sprintf('-%d days', $daysBefore));
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);
        new ExtractWriter()->write($path, $provider->default(), $rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $importer->import($path, ImportRangeDto::create($date, $date, 31), $provider->default(), null, 'base.xlsx');
        unlink($path);
    }

    /**
     * Imports the base scenario on 5 January 2026, and the base scenario with no route BOOKED on
     * 5 December 2025. December has 6 trips, 2 of 3 runs on time, 1 of 3 real solo runs and 1 of 6
     * trips cancelled. January has 75 %, 50 % and 1 of 7 trips cancelled.
     */
    private function clientWithAComparison(): KernelBrowser
    {
        $client = $this->client('viewer', importBase: true);
        $this->importBase(31, 'BOOKED');

        return $client;
    }

    private static function value(Crawler $crawler, string $test): string
    {
        return $crawler->filter(\sprintf('[data-test="%s-value"]', $test))->text();
    }
}

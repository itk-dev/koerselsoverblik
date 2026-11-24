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
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the "Kørselsaktivitet" page: the access, the filter bar and the figures of the base
 * scenario. The tests find the figures by their data-test attributes.
 */
final class ActivityPageTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use LoginTrait;

    private const string SCENARIOS = __DIR__.'/../fixtures/driving_data';

    public function testAnonymousUserMustSignIn(): void
    {
        $client = static::createClient();

        $client->request('GET', '/activity');

        self::assertResponseRedirects('/openidconnect/login/default');
    }

    public function testUserWithNoPermissionRoleIsForbidden(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'user');

        $client->request('GET', '/activity');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testViewerSeesThePage(): void
    {
        $crawler = $this->page('viewer');

        self::assertResponseIsSuccessful();
        self::assertSame('Kørselsaktivitet', $crawler->filter('h1')->text());
        self::assertSame('Kørselsaktivitet', trim($crawler->filter('[data-test="menu-item"][aria-current="page"]')->text()));
        self::assertSame('Kørselsaktivitet – Kørselsoverblik', $crawler->filter('title')->text());
    }

    public function testNoTextNamesThePlanningSystem(): void
    {
        $client = $this->client('viewer', importBase: true);
        $client->request('GET', '/activity');

        self::assertStringNotContainsString('Optiruns', (string) $client->getResponse()->getContent());
    }

    /**
     * The values of the base scenario, on 5 January 2026: 7 trips, of which 5 are completed, 1 is
     * cancelled and 1 has no registration, and 4 runs. The routes have no supplier.
     */
    public function testBaseScenario(): void
    {
        $crawler = $this->page('viewer', importBase: true);

        self::assertSame('januar 2026', $crawler->filter('[data-test="period"]')->text());
        self::assertSame('7', self::value($crawler, 'trips'));
        self::assertSame('Også de aflyste ture · 1 uden registrering', $crawler->filter('[data-test="trips"] .tile__note')->text());
        self::assertSame('5', self::value($crawler, 'completed'));
        self::assertSame('71 % af de bestilte ture', $crawler->filter('[data-test="completed"] .tile__note')->text());
        self::assertSame('14 %', self::value($crawler, 'cancelled'));
        self::assertSame('1 aflyste ture', $crawler->filter('[data-test="cancelled"] .tile__note')->text());
        self::assertSame('4', self::value($crawler, 'runs'));
        // December 2025 has no data, so there is no change.
        foreach (['trips', 'completed', 'cancelled', 'runs'] as $tile) {
            self::assertSame('ingen sammenligning', $crawler->filter(\sprintf('[data-test="%s-change"]', $tile))->text());
        }

        // The bar of the period has the three statuses, and the total is all trips.
        self::assertSame('7 ture', $crawler->filter('[data-test="outcome-period"] .dist__total')->text());
        // The hidden data table of the columns gives the counts of January.
        self::assertSame(
            ['jan', '5', '1', '1'],
            $crawler->filter('#outcome-months table tbody tr')->last()->filter('th, td')->each(static fn (Crawler $cell): string => $cell->text()),
        );
        self::assertCount(1, $crawler->filter('#cancelled-share svg'));

        // The share is of the trips of all rows: 6 of 7 and 1 of 7.
        self::assertSame(
            [['R', 'Ukendt', '6', '86 %'], ['BOOKED', 'Ukendt', '1', '14 %']],
            self::rows($crawler, 'sub-agreement-row'),
        );
        // 5 completed trips on 4 runs.
        self::assertSame(
            [['Ukendt', '7', '5', '1', '14 %', '4', '1,3']],
            self::rows($crawler, 'activity-supplier-row'),
        );

        // Each tile has a "?".
        self::assertCount(4, $crawler->filter('.tiles [popover]'));
        // Each chart has a hidden data table.
        self::assertCount(2, $crawler->filter('figure.chart .sr-only table'));
    }

    /**
     * With a supplier, the figures and the table of the sub-agreements only have the routes of
     * the supplier. The table of the suppliers has all suppliers.
     */
    public function testSupplierFilter(): void
    {
        $client = $this->client('viewer', importBase: true);
        $supplier = $this->supplier('Vognmand A');
        $entityManager = $this->entityManager();
        $subAgreement = $entityManager->getRepository(SubAgreement::class)->findOneBy(['reference' => 'BOOKED']);
        self::assertInstanceOf(SubAgreement::class, $subAgreement);
        $subAgreement->setSupplier($supplier);
        $entityManager->flush();

        $crawler = $client->request('GET', '/activity?s='.$supplier->getId());

        self::assertSame('1', self::value($crawler, 'trips'));
        self::assertSame([['BOOKED', 'Vognmand A', '1', '100 %']], self::rows($crawler, 'sub-agreement-row'));
        self::assertSame(['Vognmand A', 'Ukendt'], array_map(static fn (array $row): string => $row[0], self::rows($crawler, 'activity-supplier-row')));
    }

    /**
     * The figures do not include the routes of an ignored sub-agreement. The lede tells this for
     * all figures of the page, so the panel of the sub-agreements does not tell it again. The
     * page does not name the ignored sub-agreements.
     */
    public function testIgnoredSubAgreementIsNotCounted(): void
    {
        $client = $this->client('viewer', importBase: true);
        $entityManager = $this->entityManager();
        $subAgreement = $entityManager->getRepository(SubAgreement::class)->findOneBy(['reference' => 'BOOKED']);
        self::assertInstanceOf(SubAgreement::class, $subAgreement);
        $subAgreement->setIgnored(true);
        $entityManager->flush();

        $crawler = $client->request('GET', '/activity');

        self::assertStringEndsWith('Ignorerede delaftaler indgår ikke i tallene.', $crawler->filter('[data-test="lede"]')->text());
        self::assertStringNotContainsString('BOOKED', $crawler->filter('.page-head')->text());
        self::assertStringNotContainsString('Ignorerede', $crawler->filter('[data-test="sub-agreements"] .panel__foot')->text());
        self::assertSame('6', self::value($crawler, 'trips'));
        self::assertSame([['R', 'Ukendt', '6', '100 %']], self::rows($crawler, 'sub-agreement-row'));
    }

    /**
     * A month with no data shows "–", not 0.
     */
    public function testMonthWithNoDataHasNoValues(): void
    {
        $crawler = $this->page('viewer', '?d=2025-12-01', importBase: true);

        self::assertSame('december 2025', $crawler->filter('[data-test="period"]')->text());
        foreach (['trips', 'completed', 'cancelled', 'runs'] as $tile) {
            self::assertSame('–', self::value($crawler, $tile));
            self::assertCount(0, $crawler->filter(\sprintf('[data-test="%s"] .tile__note', $tile)));
        }
        self::assertSame([], self::rows($crawler, 'sub-agreement-row'));
        self::assertSame([], self::rows($crawler, 'activity-supplier-row'));
        self::assertCount(2, $crawler->filter('[data-test="no-data"]'));
    }

    /**
     * The tiles show the whole week, but the sparklines and the charts show months.
     */
    public function testPeriodThatIsNotAMonthHasANoteOfTheMonths(): void
    {
        $client = $this->client('viewer');

        self::assertSame('Kurverne viser hele måneder.', $client->request('GET', '/activity?p=week')->filter('[data-test="months-note"]')->text());
        self::assertCount(0, $client->request('GET', '/activity')->filter('[data-test="months-note"]'));
    }

    /**
     * Each view has one URL. The form sends the default values, so the page redirects them.
     */
    public function testQueryThatIsNotCanonicalRedirects(): void
    {
        $client = $this->client('viewer');
        $client->request('GET', '/activity?c=last_year&s=');

        self::assertResponseRedirects('/activity?c=last_year');
    }

    /**
     * The links of the filter bar stay on the page.
     */
    public function testLinksToThePreviousMonth(): void
    {
        $crawler = $this->page('viewer');

        self::assertSame('/activity?d=2025-12-01', $crawler->filter('a[data-test="previous-period"]')->attr('href'));
    }

    /**
     * The Content Security Policy does not allow style attributes, and a script needs the nonce.
     */
    public function testPageFollowsTheContentSecurityPolicy(): void
    {
        $client = $this->client('viewer', importBase: true);
        $crawler = $client->request('GET', '/activity');

        self::assertStringNotContainsString('style=', (string) $client->getResponse()->getContent());
        foreach ($crawler->filter('script') as $script) {
            self::assertInstanceOf(\DOMElement::class, $script);
            self::assertNotSame('', $script->getAttribute('nonce'));
        }
    }

    private function page(string $user, string $query = '', bool $importBase = false): Crawler
    {
        return $this->client($user, $importBase)->request('GET', '/activity'.$query);
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
        $rows = new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml')->rows;
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);
        new ExtractWriter()->write($path, $provider->default(), $rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $importer->import($path, ImportRangeDto::create(new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-01-05'), 31), $provider->default(), null, 'base.xlsx');
        unlink($path);
    }

    private function supplier(string $name): Supplier
    {
        $supplier = new Supplier()->setName($name);
        $entityManager = $this->entityManager();
        $entityManager->persist($supplier);
        $entityManager->flush();

        return $supplier;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
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

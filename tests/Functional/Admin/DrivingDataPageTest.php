<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\ImportBatch;
use App\Entity\RejectedStopRow;
use App\Entity\Route;
use App\Entity\StopRow;
use App\Entity\SubAgreement;
use App\Import\DrivingDataImporter;
use App\Import\Extract\StopRowDto;
use App\Import\ImportRangeDto;
use App\Import\Mapping\MappingProvider;
use App\Import\Mapping\StopStatus;
use App\Import\Mapping\StopType;
use App\Repository\RejectedStopRowRepository;
use App\Repository\StopRowRepository;
use App\Tests\BaseScenarioTrait;
use App\Tests\Functional\LoginTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the lists of the stop rows and the rejected rows of a range, and the detail pages with
 * the copy of a row. No page shows the person id.
 */
final class DrivingDataPageTest extends WebTestCase
{
    use BaseScenarioTrait;
    use ClockSensitiveTrait;
    use LoginTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::mockTime('2026-01-06 10:15:00');
    }

    public function testUserWithoutTheDrivingDataRoleIsDenied(): void
    {
        $this->loginAs($this->client, 'viewer');
        // The row must exist, because Symfony looks for the row before it checks the role.
        $this->importScenario('bad_values', '2026-01-05');
        $stopRow = $this->firstStopRow()->getId();
        $rejectedRow = $this->rejectedRows()[0]->getId();

        foreach (['/admin/driving-data', '/admin/driving-data/rows', '/admin/driving-data/stop-row/'.$stopRow, '/admin/driving-data/rejected-row/'.$rejectedRow] as $url) {
            $this->client->request('GET', $url);

            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN, $url);
        }
    }

    /**
     * With no query, the range is yesterday.
     */
    public function testNoQueryShowsTheRowsOfYesterday(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2026-01-04');
        $this->importBaseOn('2026-01-05');

        $crawler = $this->client->request('GET', '/admin/driving-data/rows');

        self::assertResponseIsSuccessful();
        self::assertSame('2026-01-05', $crawler->filter('#firstDate')->attr('value'));
        self::assertSame('2026-01-05', $crawler->filter('#lastDate')->attr('value'));
        self::assertCount(14, $crawler->filter('[data-test="stop-row"]'));
        self::assertSame('14', $crawler->filter('[data-test="stop-rows-total"]')->text());
        $this->assertNoPersonalData();
    }

    public function testStopRowsShowTheTypedFieldsInOrder(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2026-01-05');

        $crawler = $this->client->request('GET', '/admin/driving-data/rows?firstDate=2026-01-05&lastDate=2026-01-05');

        $first = $crawler->filter('[data-test="stop-row"]')->first()->filter('td')->each(static fn (Crawler $cell): string => $cell->text());
        self::assertSame(['05-01-2026', 'BOOKED'], \array_slice($first, 0, 2));
        $routes = $crawler->filter('[data-test="stop-row"] td:nth-child(2)')->each(static fn (Crawler $cell): string => $cell->text());
        $sorted = $routes;
        sort($sorted);
        self::assertSame($sorted, $routes);
        self::assertSame(['BOOKED', 'R-01', 'R-02'], array_values(array_unique($routes)));
    }

    /**
     * The route filter limits the stop rows. A rejected row has no route, so the filter does not
     * apply to the rejected rows.
     */
    public function testRouteFilterLimitsTheStopRows(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2026-01-05');

        $crawler = $this->client->request('GET', '/admin/driving-data/rows?firstDate=2026-01-05&lastDate=2026-01-05&route=r-02');

        $routes = $crawler->filter('[data-test="stop-row"] td:nth-child(2)')->each(static fn (Crawler $cell): string => $cell->text());
        self::assertNotSame([], $routes);
        self::assertSame(['R-02'], array_values(array_unique($routes)));
        self::assertSame('r-02', $crawler->filter('#route')->attr('value'));
    }

    public function testRangeThatIsTooLongShowsAnErrorAndNoLists(): void
    {
        $this->loginAs($this->client, 'admin');

        $crawler = $this->client->request('GET', '/admin/driving-data/rows?firstDate=2026-01-01&lastDate=2026-01-08');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('Perioden må højst være 7 dage.', $crawler->filter('[data-test="rows-form"]')->text());
        self::assertCount(0, $crawler->filter('[data-test="stop-rows"]'));
        self::assertCount(0, $crawler->filter('[data-test="rejected-rows"]'));
    }

    public function testLastDateBeforeTheFirstDateShowsAnError(): void
    {
        $this->loginAs($this->client, 'admin');

        $crawler = $this->client->request('GET', '/admin/driving-data/rows?firstDate=2026-01-05&lastDate=2026-01-04');

        self::assertStringContainsString('Den sidste dato er før den første dato.', $crawler->filter('[data-test="rows-form"]')->text());
        self::assertCount(0, $crawler->filter('[data-test="stop-rows"]'));
    }

    /**
     * A page has 100 rows. The links keep the range and the page of the other list. A page after
     * the last page gives the last page.
     */
    public function testStopRowsHaveAPageOf100Rows(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->storeStopRows(101);

        $crawler = $this->client->request('GET', '/admin/driving-data/rows?firstDate=2026-01-05&lastDate=2026-01-05&rejectedPage=1');

        self::assertCount(100, $crawler->filter('[data-test="stop-row"]'));
        self::assertSame('Side 1 af 2', $crawler->filter('[data-test="stop-rows"] [data-test="page"]')->text());
        $next = (string) $crawler->filter('[data-test="stop-rows"] [data-test="next-page"]')->attr('href');
        self::assertStringContainsString('stopPage=2', $next);
        self::assertStringContainsString('firstDate=2026-01-05', $next);
        self::assertStringContainsString('rejectedPage=1', $next);

        $crawler = $this->client->request('GET', $next);

        self::assertCount(1, $crawler->filter('[data-test="stop-row"]'));
        self::assertCount(0, $crawler->filter('[data-test="stop-rows"] [data-test="next-page"]'));

        $crawler = $this->client->request('GET', '/admin/driving-data/rows?firstDate=2026-01-05&lastDate=2026-01-05&stopPage=9');

        self::assertCount(1, $crawler->filter('[data-test="stop-row"]'));

        $crawler = $this->client->request('GET', '/admin/driving-data/rows?firstDate=2026-01-05&lastDate=2026-01-05&stopPage=0');

        self::assertCount(100, $crawler->filter('[data-test="stop-row"]'));
    }

    /**
     * A query cannot sort the list by another field.
     */
    public function testSortQueryIsIgnored(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2026-01-05');

        $crawler = $this->client->request('GET', '/admin/driving-data/rows?firstDate=2026-01-05&lastDate=2026-01-05&sort=row.personId&direction=desc');

        self::assertResponseIsSuccessful();
        self::assertSame('BOOKED', $crawler->filter('[data-test="stop-row"] td:nth-child(2)')->first()->text());
    }

    /**
     * A filter on the person id would tell if a row has a person id, so a query cannot filter.
     */
    public function testFilterQueryIsNotFound(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2026-01-05');

        $this->client->request('GET', '/admin/driving-data/rows?firstDate=2026-01-05&lastDate=2026-01-05&filterField=row.personId&filterValue=x');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRejectedRowsShowTheReasonAndTheField(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importScenario('bad_values', '2026-01-05');

        $crawler = $this->client->request('GET', '/admin/driving-data/rows?firstDate=2026-01-05&lastDate=2026-01-05');

        $rows = $crawler->filter('[data-test="rejected-row"]');
        self::assertCount(5, $rows);
        self::assertSame('5', $crawler->filter('[data-test="rejected-rows-total"]')->text());
        $texts = $rows->each(static fn (Crawler $row): string => $row->text());
        self::assertStringContainsString('Vognløb', $texts[0]);
        self::assertStringContainsString('Feltet er tomt.', $texts[0]);
        self::assertStringContainsString('bad_values.xlsx', $texts[0]);
        $this->assertNoPersonalData();
    }

    public function testStopRowPageShowsTheFieldsAndTheCopy(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2026-01-05');
        $row = $this->firstStopRow();

        $crawler = $this->client->request('GET', '/admin/driving-data/stop-row/'.$row->getId());

        self::assertResponseIsSuccessful();
        self::assertSame('(skjult)', $crawler->filter('[data-test="person-id"]')->text());
        $columns = $crawler->filter('[data-test="source-column"]')->each(static fn (Crawler $cell): string => $cell->text());
        self::assertContains('RouteName', $columns);
        self::assertContains('PersonID', $columns);
        self::assertSame('(skjult)', $crawler->filter('[data-test="source-row"] [data-test="hidden-value"]')->text());
        self::assertStringContainsString($row->getRoute()->getSourceKey(), $crawler->filter('[data-test="source-row"]')->text());
        self::assertSame(
            '/admin/driving-data/rows?firstDate=2026-01-05&lastDate=2026-01-05',
            $crawler->filter('[data-test="back"]')->attr('href'),
        );
        $this->assertNoPersonalData();
    }

    /**
     * The cell of an empty required field is not in the copy. It still shows as the cause.
     */
    public function testRejectedRowPageShowsTheCause(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importScenario('bad_values', '2026-01-05');
        $rows = $this->rejectedRows();

        $crawler = $this->client->request('GET', '/admin/driving-data/rejected-row/'.$rows[0]->getId());

        self::assertResponseIsSuccessful();
        self::assertSame('Feltet er tomt.', $crawler->filter('[data-test="reason"]')->text());
        $cause = $crawler->filter('[data-test="cause"]');
        self::assertCount(1, $cause);
        self::assertStringContainsString('RouteName', $cause->text());
        self::assertStringContainsString('(tom)', $cause->text());

        $crawler = $this->client->request('GET', '/admin/driving-data/rejected-row/'.$rows[1]->getId());

        self::assertStringContainsString('abc', $crawler->filter('[data-test="cause"]')->text());
        $this->assertNoPersonalData();
    }

    /**
     * The mapping of the upload tells which column has the person id. With no mapping, the page
     * shows no copy.
     */
    public function testRowOfAMappingThatNoLongerExistsShowsNoCopy(): void
    {
        $this->loginAs($this->client, 'admin');
        $row = $this->storeStopRows(1, 'removed')[0];

        $crawler = $this->client->request('GET', '/admin/driving-data/stop-row/'.$row);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test="mapping-missing"]'));
        self::assertCount(0, $crawler->filter('[data-test="source-row"]'));
    }

    public function testUnknownRowIsNotFound(): void
    {
        $this->loginAs($this->client, 'admin');

        $this->client->request('GET', '/admin/driving-data/stop-row/999999');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('GET', '/admin/driving-data/rejected-row/999999');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * No response shows a person id of the scenarios, and no template has a style attribute.
     */
    private function assertNoPersonalData(): void
    {
        $content = (string) $this->client->getResponse()->getContent();
        self::assertDoesNotMatchRegularExpression('/P-0\d\d/', $content);
        self::assertStringNotContainsString('style=', $content);
    }

    /**
     * Stores stop rows of one route on 5 January 2026, with one copy each.
     *
     * @return list<int> the ids of the rows
     */
    private function storeStopRows(int $count, string $mappingName = 'default'): array
    {
        $entityManager = $this->entityManager();
        $day = new \DateTimeImmutable('2026-01-05');
        $batch = new ImportBatch($mappingName, 'extract.xlsx', null, new \DateTimeImmutable(), ImportRangeDto::create($day, $day, 7));
        $subAgreement = new SubAgreement()->setReference('A');
        $route = new Route('A-01', $subAgreement);
        $rows = [];
        for ($index = 0; $index < $count; ++$index) {
            $rows[] = new StopRow(new StopRowDto(
                rowNumber: $index + 4,
                rowKey: 'S-'.$index,
                route: 'A-01',
                personId: null,
                serviceDate: $day,
                run: 1,
                stopType: StopType::Pickup,
                stopStatus: StopStatus::Completed,
                passengersAfter: null,
                nextDistanceKm: null,
                nextDurationMin: null,
                plannedTime: $day->setTime(7, 0)->modify('+'.$index.' minutes'),
                actualTime: null,
                plannedSolo: null,
                locationName: null,
                sourceValues: ['RouteName' => 'A-01'],
            ), $mappingName, $route, $batch);
        }
        foreach ([$batch, $subAgreement, $route, ...$rows] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $ids = array_map(static fn (StopRow $row): int => (int) $row->getId(), $rows);
        $entityManager->clear();

        return $ids;
    }

    private function importScenario(string $name, string $date): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);
        new ExtractWriter()->write($path, $provider->default(), new ScenarioLoader()->load(__DIR__.'/../../fixtures/driving_data/'.$name.'.yaml')->rows);
        $importer = self::getContainer()->get(DrivingDataImporter::class);
        self::assertInstanceOf(DrivingDataImporter::class, $importer);
        $day = new \DateTimeImmutable($date);
        $importer->import($path, ImportRangeDto::create($day, $day, 7), $provider->default(), null, $name.'.xlsx');
        unlink($path);
    }

    private function firstStopRow(): StopRow
    {
        $rows = self::getContainer()->get(StopRowRepository::class);
        self::assertInstanceOf(StopRowRepository::class, $rows);
        $row = $rows->findOneBy([], ['id' => 'ASC']);
        self::assertInstanceOf(StopRow::class, $row);

        return $row;
    }

    /**
     * @return list<RejectedStopRow> in the order of the row number
     */
    private function rejectedRows(): array
    {
        $rows = self::getContainer()->get(RejectedStopRowRepository::class);
        self::assertInstanceOf(RejectedStopRowRepository::class, $rows);

        return $rows->findBy([], ['rowNumber' => 'ASC']);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}

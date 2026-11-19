<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\ImportBatch;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingProvider;
use App\Tests\Functional\LoginTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the bulk upload: the page, and the two requests that the browser sends for each file.
 * The first request checks one file. The second imports it. Each response is one row of the list.
 */
final class BulkImportPageTest extends WebTestCase
{
    use LoginTrait;

    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    private KernelBrowser $client;

    private string $path = '';

    private string $csrfToken = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->path = sys_get_temp_dir().'/'.uniqid('extract_', true).'.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        foreach (glob($this->uploadDirectory().'/*.xlsx') ?: [] as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    public function testUserWithoutTheDrivingDataRoleIsDenied(): void
    {
        $this->loginAs($this->client, 'viewer');

        $this->client->request('GET', '/admin/import/bulk');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->client->request('POST', '/admin/import/bulk/check');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * Tabs switch between the upload of one file and the bulk upload.
     */
    #[DataProvider('tabsProvider')]
    public function testTabsSwitchBetweenTheUploadPages(string $url, string $current): void
    {
        $this->loginAs($this->client, 'admin');

        $crawler = $this->client->request('GET', $url);

        $tabs = $crawler->filter('[data-test="upload-tabs"] a');
        self::assertSame(['/admin/import', '/admin/import/bulk'], $tabs->each(static fn (Crawler $tab): ?string => $tab->attr('href')));
        self::assertSame([$current], $crawler->filter('[data-test="upload-tabs"] a[aria-current="page"]')->each(static fn (Crawler $tab): string => $tab->text()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tabsProvider(): iterable
    {
        yield 'one file' => ['/admin/import', 'Én fil'];
        yield 'many files' => ['/admin/import/bulk', 'Mange filer'];
    }

    public function testThePageHasADropAreaForManyFiles(): void
    {
        $this->loginAs($this->client, 'admin');

        $crawler = $this->openPage();

        self::assertSame('Upload mange filer', $crawler->filter('h1')->text());
        // The limit of APP_DRIVING_DATA_MAX_DAYS in the test environment.
        self::assertSame('Hver fil må højst have stoprækker på 7 dage. En fil med flere dage fejler. Del et længere udtræk op i perioder på højst 7 dage, for eksempel én fil for hver uge.', $crawler->filter('[data-test="bulk-max-days"]')->text());
        self::assertCount(1, $crawler->filter('[data-controller~="bulk-upload"] input[type="file"][multiple]'));
        self::assertSame('Træk filerne hertil, eller klik for at vælge dem. Du kan vælge mange filer på én gang.', $crawler->filter('.dropzone-placeholder')->text());
        self::assertSame('/admin/import/bulk/check', $crawler->filter('[data-controller~="bulk-upload"]')->attr('data-bulk-upload-check-url-value'));
    }

    /**
     * The import button opens a dialog that tells the user to keep the page open. The import
     * starts only with the button "import" of the dialog.
     */
    public function testTheImportButtonAsksForAConfirmation(): void
    {
        $this->loginAs($this->client, 'admin');

        $crawler = $this->openPage();

        self::assertSame('bulk-upload#confirm', $crawler->filter('[data-bulk-upload-target="importButton"]')->attr('data-action'));
        $dialog = $crawler->filter('dialog[data-test="bulk-confirm"]');
        self::assertSame('close->bulk-upload#confirmed', $dialog->attr('data-action'));
        self::assertSame('Hold siden åben under importen', $dialog->filter('h2')->text());
        self::assertSame('checked', $dialog->filter('[data-bulk-upload-target="count"]')->attr('data-state'));
        self::assertSame(['itkaarhus-tag itkaarhus-tag--info', 'Kontrolleret 0'], [$dialog->filter('.bulk-upload__counts li')->attr('class'), $dialog->filter('.bulk-upload__counts li')->text()]);
        self::assertSame([['import', 'Importér'], ['cancel', 'Annullér']], $dialog->filter('form[method="dialog"] button')->each(static fn (Crawler $button): array => [$button->attr('value'), $button->text()]));
    }

    /**
     * The controller opens the dialog when the import is done. It shows the counts of the files.
     */
    public function testThePageHasADialogForTheEndOfTheImport(): void
    {
        $this->loginAs($this->client, 'admin');

        $dialog = $this->openPage()->filter('dialog[data-test="bulk-done"]');

        self::assertSame('Importen er færdig', $dialog->filter('h2')->text());
        self::assertSame(['imported', 'skipped', 'failed'], $dialog->filter('[data-bulk-upload-target="count"]')->each(static fn (Crawler $count): ?string => $count->attr('data-state')));
        // The same pills as the counters of the page.
        self::assertSame(
            [['itkaarhus-tag itkaarhus-tag--success', 'Importeret 0'], ['itkaarhus-tag itkaarhus-tag--warning', 'Sprunget over 0'], ['itkaarhus-tag itkaarhus-tag--danger', 'Fejlet 0']],
            $dialog->filter('.bulk-upload__counts li')->each(static fn (Crawler $pill): array => [$pill->attr('class'), $pill->text()]),
        );
        self::assertSame('dialog', $dialog->filter('form')->attr('method'));
        self::assertSame('OK', $dialog->filter('button')->text());
    }

    /**
     * The range comes from the stop rows of the file. The check changes no data.
     */
    public function testCheckReadsTheRangeAndChangesNoData(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');

        $row = $this->check('base.xlsx');

        self::assertResponseIsSuccessful();
        self::assertSame('checked', $row->attr('data-state'));
        self::assertSame('base.xlsx', $row->filter('[data-test="bulk-name"]')->text());
        self::assertSame('5. januar 2026 – 5. januar 2026', $row->filter('[data-test="bulk-range"]')->text());
        self::assertSame('14', $row->filter('[data-test="bulk-insert"]')->text());
        self::assertSame('0', $row->filter('[data-test="bulk-delete"]')->text());
        self::assertSame('0', $row->filter('[data-test="bulk-reject"]')->text());
        self::assertMatchesRegularExpression('#^/admin/import/bulk/[0-9a-f]{32}$#', (string) $row->attr('data-import-url'));
        self::assertSame(0, $this->stored('stop_row'));
        self::assertCount(1, glob($this->uploadDirectory().'/*.xlsx') ?: []);
    }

    public function testCheckOfAFileWithMoreDaysThanTheLimitFails(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('two_weeks');

        $row = $this->check('two_weeks.xlsx');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('failed', $row->attr('data-state'));
        self::assertSame('Filen har rækker på flere end 7 dage. Del udtrækket op i perioder på højst 7 dage.', $row->filter('[data-test="bulk-error"]')->text());
        self::assertNull($row->attr('data-import-url'));
        self::assertSame([], glob($this->uploadDirectory().'/*.xlsx') ?: []);
    }

    /**
     * A file with no data is skipped, not failed. It is not kept for the import.
     */
    public function testCheckSkipsAFileWithNoStopRows(): void
    {
        $this->loginAs($this->client, 'admin');
        new ExtractWriter()->write($this->path, $this->mapping(), []);

        $row = $this->check('no-rows.xlsx');

        self::assertResponseIsSuccessful();
        self::assertSame('skipped', $row->attr('data-state'));
        self::assertSame('Filen har ingen stoprækker.', $row->filter('[data-test="bulk-error"]')->text());
        self::assertNull($row->attr('data-import-url'));
        self::assertSame([], glob($this->uploadDirectory().'/*.xlsx') ?: []);
    }

    /**
     * A file with data rows that are all rejected is broken. It fails, so that the user sees it.
     */
    public function testCheckOfAFileWithOnlyRejectedRowsFails(): void
    {
        $this->loginAs($this->client, 'admin');
        $rows = array_map(
            static fn (ExtractRow $row): ExtractRow => new ExtractRow($row->values, $row->rowId, ['service_date' => 'not a date'] + $row->raw, $row->kept),
            new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml')->rows,
        );
        new ExtractWriter()->write($this->path, $this->mapping(), $rows);

        $row = $this->check('broken.xlsx');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('failed', $row->attr('data-state'));
        self::assertSame('Filen har rækker, men alle rækker er afvist. Kontrollér udtrækket, eller upload det på siden for én fil for at se årsagerne.', $row->filter('[data-test="bulk-error"]')->text());
    }

    public function testCheckSkipsAnEmptyFile(): void
    {
        $this->loginAs($this->client, 'admin');
        file_put_contents($this->path, '');

        $row = $this->check('empty.xlsx');

        self::assertResponseIsSuccessful();
        self::assertSame('skipped', $row->attr('data-state'));
        self::assertSame('Filen er tom.', $row->filter('[data-test="bulk-error"]')->text());
    }

    public function testCheckOfAFileThatIsNotXlsxFails(): void
    {
        $this->loginAs($this->client, 'admin');
        file_put_contents($this->path, 'not a spreadsheet');

        $row = $this->check('notes.txt');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('notes.txt', $row->filter('[data-test="bulk-name"]')->text());
        self::assertSame('Filen skal være et Excel-udtræk (.xlsx).', $row->filter('[data-test="bulk-error"]')->text());
    }

    public function testCheckWithNoFileFails(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->openPage();

        $crawler = $this->client->request('POST', '/admin/import/bulk/check', server: ['HTTP_X_CSRF_TOKEN' => $this->csrfToken]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('Vælg en fil.', $crawler->filter('[data-test="bulk-error"]')->text());
    }

    public function testCheckWithAnInvalidCsrfTokenIsDenied(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $this->openPage();
        $this->csrfToken = 'wrong';

        $this->check('base.xlsx');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame([], glob($this->uploadDirectory().'/*.xlsx') ?: []);
    }

    public function testImportImportsTheCheckedFile(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $url = (string) $this->check('base.xlsx')->attr('data-import-url');

        $row = $this->import($url);

        self::assertResponseIsSuccessful();
        self::assertSame('imported', $row->attr('data-state'));
        self::assertSame('14', $row->filter('[data-test="bulk-insert"]')->text());
        self::assertSame(14, $this->stored('stop_row'));
        $batch = $this->entityManager()->getRepository(ImportBatch::class)->findOneBy([]);
        self::assertInstanceOf(ImportBatch::class, $batch);
        self::assertSame('base.xlsx', $batch->getFileName());
        self::assertSame('admin@example.com', $batch->getUploadedBy()?->getEmail());
        self::assertSame('/admin/import/'.$batch->getId(), $row->filter('[data-test="bulk-result"]')->attr('href'));
        self::assertSame([], glob($this->uploadDirectory().'/*.xlsx') ?: []);
    }

    /**
     * A second request for the same file imports nothing.
     */
    public function testImportOfAnUnknownUploadFails(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $url = (string) $this->check('base.xlsx')->attr('data-import-url');
        $this->import($url);

        $row = $this->import($url);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('Uploaden er udløbet. Upload filen igen.', $row->filter('[data-test="bulk-error"]')->text());
        self::assertSame(1, $this->stored('import_batch'));
    }

    /**
     * A failed import changes no data, and the next file can still be imported.
     */
    public function testFailedImportShowsTheReason(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $this->import((string) $this->check('base.xlsx')->attr('data-import-url'));
        // The same row ids on the next day.
        $rows = array_map(
            static fn (ExtractRow $row): ExtractRow => new ExtractRow(['service_date' => new \DateTimeImmutable('2026-01-06')] + $row->values, $row->rowId, $row->raw, $row->kept),
            new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml')->rows,
        );
        new ExtractWriter()->write($this->path, $this->mapping(), $rows);
        $url = (string) $this->check('next-day.xlsx')->attr('data-import-url');

        $row = $this->import($url);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('Et række-id i filen findes allerede på en dato uden for perioden. Importen ændrede ingen data.', $row->filter('[data-test="bulk-error"]')->text());
        self::assertSame(14, $this->stored('stop_row'));
        self::assertSame([], glob($this->uploadDirectory().'/*.xlsx') ?: []);
    }

    public function testImportWithAnInvalidCsrfTokenIsDenied(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $url = (string) $this->check('base.xlsx')->attr('data-import-url');
        $this->csrfToken = 'wrong';

        $this->import($url);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(0, $this->stored('stop_row'));
    }

    private function openPage(): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/import/bulk');
        self::assertResponseIsSuccessful();
        $this->csrfToken = (string) $crawler->filter('[data-controller~="bulk-upload"]')->attr('data-bulk-upload-csrf-token-value');

        return $crawler;
    }

    /**
     * Sends the file as the browser does, and gives the row of the response.
     */
    private function check(string $fileName): Crawler
    {
        if ('' === $this->csrfToken) {
            $this->openPage();
        }
        $crawler = $this->client->request(
            'POST',
            '/admin/import/bulk/check',
            files: ['file' => new UploadedFile($this->path, $fileName, null, null, true)],
            server: ['HTTP_X_CSRF_TOKEN' => $this->csrfToken],
        );

        return $crawler->filter('[data-test="bulk-file"]');
    }

    private function import(string $url): Crawler
    {
        return $this->client->request('POST', $url, server: ['HTTP_X_CSRF_TOKEN' => $this->csrfToken])->filter('[data-test="bulk-file"]');
    }

    private function writeScenario(string $name): void
    {
        $scenario = new ScenarioLoader()->load(self::SCENARIOS.'/'.$name.'.yaml');
        new ExtractWriter()->write($this->path, $this->mapping(), $scenario->rows, $scenario->omittedFields);
    }

    private function mapping(): MappingDto
    {
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);

        return $provider->default();
    }

    private function uploadDirectory(): string
    {
        return self::getContainer()->getParameter('kernel.project_dir').'/var/uploads/test/driving_data';
    }

    private function stored(string $table): int
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $count = $connection->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}

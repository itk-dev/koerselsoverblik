<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\DataFixtures\DrivingData\ExtractRow;
use App\DataFixtures\DrivingData\ExtractWriter;
use App\DataFixtures\DrivingData\ScenarioLoader;
use App\Entity\User;
use App\Import\Mapping\FieldName;
use App\Import\Mapping\MappingDto;
use App\Import\Mapping\MappingProvider;
use App\Tests\Functional\LoginTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the upload of driving data: the form, the preview, the confirmation, the cancel and the
 * errors. No data changes before the confirmation, and no page shows a value from the extract.
 */
final class ImportPageTest extends WebTestCase
{
    use LoginTrait;

    private const string SCENARIOS = __DIR__.'/../../fixtures/driving_data';

    private KernelBrowser $client;

    private string $path = '';

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

    public function testAnonymousUserIsSentToTheLogin(): void
    {
        $this->client->request('GET', '/admin/import');

        self::assertResponseRedirects('/openidconnect/login/default');
    }

    public function testUserWithoutTheDrivingDataRoleIsDenied(): void
    {
        $this->loginAs($this->client, 'viewer');

        $this->client->request('GET', '/admin/import');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testMenuItemGoesToTheUploadPage(): void
    {
        $this->loginAs($this->client, 'admin');

        $crawler = $this->client->request('GET', '/admin/import');

        self::assertResponseIsSuccessful();
        self::assertSame('Upload kørselsdata', $crawler->filter('h1')->text());
        self::assertSame('/admin/import', $crawler->filter('[data-test="menu-item"]:contains("Upload kørselsdata")')->attr('href'));
    }

    /**
     * The preview shows the changes. The data changes only at the confirmation.
     */
    public function testUploadShowsThePreviewAndChangesNoData(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');

        $crawler = $this->upload('2026-01-05', '2026-01-05');

        self::assertSame('5. januar 2026 – 5. januar 2026', $crawler->filter('[data-test="preview-range"]')->text());
        self::assertSame('base.xlsx', $crawler->filter('[data-test="preview-file"]')->text());
        self::assertSame('14', $crawler->filter('[data-test="preview-insert"]')->text());
        self::assertSame('0', $crawler->filter('[data-test="preview-delete"]')->text());
        self::assertSame('0', $crawler->filter('[data-test="preview-reject"]')->text());
        self::assertCount(1, $crawler->filter('[data-test="confirm"]'));
        self::assertSame(0, $this->stored('stop_row'));
        self::assertCount(1, glob($this->uploadDirectory().'/*.xlsx') ?: []);
    }

    public function testConfirmImportsAndShowsTheResult(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $crawler = $this->upload('2026-01-05', '2026-01-05');

        $this->client->submit($crawler->filter('[data-test="confirm"]')->form());

        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertMatchesRegularExpression('#^/admin/import/\d+$#', (string) parse_url($this->client->getRequest()->getUri(), \PHP_URL_PATH));
        self::assertSame('5. januar 2026 – 5. januar 2026', $crawler->filter('[data-test="result-range"]')->text());
        self::assertSame('14', $crawler->filter('[data-test="result-insert"]')->text());
        self::assertSame('0', $crawler->filter('[data-test="result-delete"]')->text());
        self::assertSame('0', $crawler->filter('[data-test="result-reject"]')->text());
        self::assertSame(14, $this->stored('stop_row'));
        self::assertSame(4, $this->stored('route_run'));
        self::assertSame([], glob($this->uploadDirectory().'/*.xlsx') ?: []);
        self::assertSame('admin@example.com', $this->connection()->fetchOne('SELECT user.email FROM import_batch JOIN user ON user.id = import_batch.uploaded_by_id'));
    }

    /**
     * The confirmation removes the upload from the session before the import. A second click
     * imports nothing.
     */
    public function testSecondConfirmationImportsNothing(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $crawler = $this->upload('2026-01-05', '2026-01-05');
        $form = $crawler->filter('[data-test="confirm"]')->form();
        $this->client->submit($form);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/import');
        $crawler = $this->client->followRedirect();
        self::assertSame('Uploaden er udløbet. Upload filen igen.', $crawler->filter('[data-test="flash-error"]')->text());
        self::assertSame(1, $this->stored('import_batch'));
    }

    public function testCancelDeletesTheFileAndChangesNoData(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $crawler = $this->upload('2026-01-05', '2026-01-05');
        $preview = (string) $this->client->getRequest()->getUri();

        $this->client->submit($crawler->filter('[data-test="cancel"]')->form());

        self::assertResponseRedirects('/admin/import');
        self::assertSame([], glob($this->uploadDirectory().'/*.xlsx') ?: []);
        self::assertSame(0, $this->stored('stop_row'));
        $this->client->request('GET', $preview);
        self::assertResponseRedirects('/admin/import');
    }

    public function testPreviewListsTheDatesWithNoRows(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('range_edges');

        $crawler = $this->upload('2026-01-05', '2026-01-07');

        self::assertSame(['6. januar 2026'], $crawler->filter('[data-test="empty-date"]')->each(static fn (Crawler $item): string => $item->text()));
    }

    /**
     * The rejected rows and the warnings are counted for each field and reason, in Danish, with no
     * values.
     */
    public function testPreviewShowsTheRejectedRowsAndTheWarningsWithNoValues(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('bad_values');

        $crawler = $this->upload('2026-01-05', '2026-01-05');

        self::assertSame('5', $crawler->filter('[data-test="preview-reject"]')->text());
        self::assertSame('3', $crawler->filter('[data-test="preview-warnings"]')->text());
        self::assertGreaterThan(0, $crawler->filter('[data-test="rejection-row"]')->count());
        self::assertGreaterThan(0, $crawler->filter('[data-test="warning-row"]')->count());
        self::assertStringNotContainsString('upload.reason.', $crawler->html());
        self::assertStringNotContainsString('upload.field.', $crawler->html());
        foreach (['abc', 'not-a-date', 'late', 'UNKNOWN', 'P-00'] as $value) {
            self::assertStringNotContainsString($value, $crawler->filter('main')->text());
        }
    }

    /**
     * A wrong range gives a file with no stop rows in it. The import would only delete data.
     */
    public function testFileWithNoStopRowsInTheRangeHasNoConfirmation(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');

        $crawler = $this->upload('2026-02-05', '2026-02-05');

        self::assertCount(0, $crawler->filter('[data-test="confirm"]'));
        self::assertSame('Filen har ingen stoprækker i perioden. Den kan ikke importeres.', $crawler->filter('[data-test="no-stop-rows"]')->text());
    }

    /**
     * @param array<string, string> $dates
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidForms')]
    public function testInvalidFormShowsAnError(string $fileName, array $dates, string $message): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $path = sys_get_temp_dir().'/'.$fileName;
        copy($this->path, $path);

        $crawler = $this->client->request('GET', '/admin/import');
        $form = $crawler->filter('[data-test="upload-form"]')->form();
        $form['import[file]']->upload($path);
        $form['import[firstDate]'] = $dates['first'];
        $form['import[lastDate]'] = $dates['last'];
        $crawler = $this->client->submit($form);
        unlink($path);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString($message, $crawler->filter('[data-test="upload-form"]')->text());
        self::assertSame([], glob($this->uploadDirectory().'/*.xlsx') ?: []);
    }

    /**
     * @return iterable<string, array{string, array{first: string, last: string}, string}>
     */
    public static function invalidForms(): iterable
    {
        yield 'a file that is not xlsx' => ['extract.csv', ['first' => '2026-01-05', 'last' => '2026-01-05'], 'Filen skal være et Excel-udtræk (.xlsx).'];
        yield 'a last date before the first date' => ['extract.xlsx', ['first' => '2026-01-05', 'last' => '2026-01-04'], 'Den sidste dato er før den første dato.'];
        yield 'a range that is too long' => ['extract.xlsx', ['first' => '2026-01-01', 'last' => '2026-02-01'], 'Perioden må højst være 31 dage.'];
        yield 'no first date' => ['extract.xlsx', ['first' => '', 'last' => '2026-01-05'], 'Vælg en dato.'];
    }

    /**
     * The form has the size limit of the extract, so a larger file is rejected before the parser.
     */
    public function testFormHasTheSizeLimit(): void
    {
        $factory = self::getContainer()->get('form.factory');
        self::assertInstanceOf(\Symfony\Component\Form\FormFactoryInterface::class, $factory);

        $constraints = $factory->create(\App\Form\ImportType::class)->get('file')->getConfig()->getOption('constraints');

        self::assertIsArray($constraints);
        $file = array_values(array_filter($constraints, static fn (object $constraint): bool => $constraint instanceof \Symfony\Component\Validator\Constraints\File))[0] ?? null;
        self::assertInstanceOf(\Symfony\Component\Validator\Constraints\File::class, $file);
        self::assertSame(20971520, $file->maxSize);
    }

    /**
     * A file with a missing column cannot be read. The page shows the column names, and deletes
     * the file.
     */
    public function testFileWithAMissingColumnShowsAnError(): void
    {
        $this->loginAs($this->client, 'admin');
        $scenario = new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml');
        new ExtractWriter()->write($this->path, $this->mapping(), $scenario->rows, [FieldName::PlannedSolo]);

        $this->upload('2026-01-05', '2026-01-05', follow: false);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseRedirects('/admin/import');
        $crawler = $this->client->followRedirect();

        self::assertSame('Filen mangler disse kolonner fra opsætningen: kørselstype.', $crawler->filter('[data-test="flash-error"]')->text());
        self::assertSame([], glob($this->uploadDirectory().'/*.xlsx') ?: []);
    }

    /**
     * A zip file with the name of an extract is not a workbook. The page shows the problem in
     * Danish.
     */
    public function testFileThatIsNotAWorkbookShowsAnError(): void
    {
        $this->loginAs($this->client, 'admin');
        $zip = new \ZipArchive();
        $zip->open($this->path, \ZipArchive::CREATE);
        $zip->addFromString('notes.txt', 'Ikke et regneark');
        $zip->close();

        $this->upload('2026-01-05', '2026-01-05', follow: false);
        $this->client->followRedirect();
        $crawler = $this->client->followRedirect();

        self::assertSame('Filen er ikke et gyldigt Excel-udtræk.', $crawler->filter('[data-test="flash-error"]')->text());
        self::assertSame([], glob($this->uploadDirectory().'/*.xlsx') ?: []);
    }

    /**
     * A row id that is stored on a date outside the range stops the import. No data changes, and
     * the page renders after Doctrine has closed the entity manager.
     */
    public function testRowIdThatIsStoredOutsideTheRangeShowsAnError(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $crawler = $this->upload('2026-01-05', '2026-01-05');
        $this->client->submit($crawler->filter('[data-test="confirm"]')->form());
        $rows = array_map(
            static fn (ExtractRow $row): ExtractRow => new ExtractRow(['service_date' => new \DateTimeImmutable('2026-01-06')] + $row->values, $row->rowId, $row->raw, $row->kept),
            new ScenarioLoader()->load(self::SCENARIOS.'/base.yaml')->rows,
        );
        new ExtractWriter()->write($this->path, $this->mapping(), $rows);
        $crawler = $this->upload('2026-01-06', '2026-01-06');

        $this->client->submit($crawler->filter('[data-test="confirm"]')->form());

        self::assertResponseRedirects('/admin/import');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame('Et række-id i filen findes allerede på en dato uden for perioden. Importen ændrede ingen data.', $crawler->filter('[data-test="flash-error"]')->text());
        self::assertSame(14, $this->stored('stop_row'));
        self::assertSame([], glob($this->uploadDirectory().'/*.xlsx') ?: []);
    }

    public function testConfirmationWithNoCsrfTokenIsDenied(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $crawler = $this->upload('2026-01-05', '2026-01-05');
        $action = (string) $crawler->filter('[data-test="confirm"]')->form()->getUri();

        $this->client->request('POST', $action);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(0, $this->stored('stop_row'));
    }

    /**
     * The upload is in the session of the user who uploaded it. Another session cannot confirm
     * it.
     */
    public function testConfirmationFromAnotherSessionImportsNothing(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        $crawler = $this->upload('2026-01-05', '2026-01-05');
        $action = (string) $crawler->filter('[data-test="confirm"]')->form()->getUri();

        $this->client->getCookieJar()->clear();
        $other = new User('other@example.com');
        $other->setRoles(['ROLE_ADMIN']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->persist($other);
        $entityManager->flush();
        $this->client->loginUser($other);
        $crawler = $this->client->request('GET', '/admin/import');
        $token = (string) $crawler->filter('[data-test="upload-form"] input[name="import[_token]"]')->attr('value');
        $this->client->request('POST', $action, ['_token' => $token]);

        self::assertSame(0, $this->stored('stop_row'));
    }

    public function testResultOfAnUnknownImportIsNotFound(): void
    {
        $this->loginAs($this->client, 'admin');

        $this->client->request('GET', '/admin/import/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Each upload deletes the upload files that are older than one hour.
     */
    public function testUploadDeletesTheOldFiles(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->writeScenario('base');
        is_dir($this->uploadDirectory()) || mkdir($this->uploadDirectory(), 0o777, true);
        $old = $this->uploadDirectory().'/old.xlsx';
        file_put_contents($old, 'x');
        touch($old, time() - 7200);

        $this->upload('2026-01-05', '2026-01-05');

        self::assertFileDoesNotExist($old);
    }

    private function upload(string $firstDate, string $lastDate, bool $follow = true): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/import');
        $form = $crawler->filter('[data-test="upload-form"]')->form();
        $form['import[file]']->upload($this->path);
        $form['import[firstDate]'] = $firstDate;
        $form['import[lastDate]'] = $lastDate;
        $crawler = $this->client->submit($form);

        return $follow && $this->client->getResponse()->isRedirect() ? $this->client->followRedirect() : $crawler;
    }

    private function writeScenario(string $name): void
    {
        $scenario = new ScenarioLoader()->load(self::SCENARIOS.'/'.$name.'.yaml');
        new ExtractWriter()->write($this->path, $this->mapping(), $scenario->rows, $scenario->omittedFields);
        // The page shows the name of the uploaded file.
        $named = \dirname($this->path).'/'.$name.'.xlsx';
        rename($this->path, $named);
        $this->path = $named;
    }

    private function mapping(): MappingDto
    {
        $provider = self::getContainer()->get(MappingProvider::class);
        self::assertInstanceOf(MappingProvider::class, $provider);

        return $provider->default();
    }

    private function uploadDirectory(): string
    {
        $directory = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($directory);

        return $directory.'/var/uploads/driving_data';
    }

    private function stored(string $table): int
    {
        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function connection(): \Doctrine\DBAL\Connection
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager->getConnection();
    }
}

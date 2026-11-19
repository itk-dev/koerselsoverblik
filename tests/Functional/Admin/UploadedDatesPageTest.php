<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\BaseScenarioTrait;
use App\Tests\Functional\LoginTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the uploaded dates on the upload page, and the clear of the driving data of a date.
 */
final class UploadedDatesPageTest extends WebTestCase
{
    use BaseScenarioTrait;
    use ClockSensitiveTrait;
    use LoginTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::mockTime('2026-01-20 10:15:00');
    }

    /**
     * The page shows the dates of the current month with stop rows, with the counts and the last
     * upload. A console upload shows "konsol".
     */
    public function testPageListsTheDatesOfTheCurrentMonth(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2026-01-05', $this->user('admin@example.com'));
        $this->importBaseOn('2026-01-06');

        $crawler = $this->client->request('GET', '/admin/import');

        self::assertResponseIsSuccessful();
        self::assertSame('januar 2026', $crawler->filter('[data-test="month"]')->text());
        self::assertSame(
            [
                ['5. januar 2026', '14', '4', '0', '2026-01-05.xlsx', 'admin@example.com', '20. januar 2026 kl. 10:15'],
                ['6. januar 2026', '14', '4', '0', '2026-01-06.xlsx', 'konsol', '20. januar 2026 kl. 10:15'],
            ],
            $crawler->filter('[data-test="uploaded-date"]')->each(static fn (Crawler $row): array => $row->filter('td:not(.actions)')->each(static fn (Crawler $cell): string => $cell->text())),
        );
        // No page shows a person id or another value from the extract.
        self::assertStringNotContainsString('P-001', (string) $this->client->getResponse()->getContent());
    }

    /**
     * The next month is after the current month, so it has no link. The batches stay after a
     * clear, so a month before the first batch has no driving data, and it has no link.
     */
    public function testMonthLinksStopAtTheCurrentMonthAndAtTheFirstBatch(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2025-12-05');

        $crawler = $this->client->request('GET', '/admin/import');

        self::assertSame('/admin/import?month=2025-12', $crawler->filter('[data-test="previous-month"]')->attr('href'));
        self::assertCount(0, $crawler->filter('[data-test="next-month"]'));

        $crawler = $this->client->request('GET', '/admin/import?month=2025-12');

        self::assertCount(0, $crawler->filter('[data-test="previous-month"]'));
    }

    public function testNoBatchesGivesNoPreviousMonth(): void
    {
        $this->loginAs($this->client, 'admin');

        $crawler = $this->client->request('GET', '/admin/import');

        self::assertCount(0, $crawler->filter('[data-test="previous-month"]'));
    }

    public function testMonthQueryShowsTheDatesOfThatMonth(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2025-12-05');
        $this->importBaseOn('2026-01-05');

        $crawler = $this->client->request('GET', '/admin/import?month=2025-12');

        self::assertSame('december 2025', $crawler->filter('[data-test="month"]')->text());
        self::assertSame(['5. december 2025'], $crawler->filter('[data-test="uploaded-date"] td:first-child')->each(static fn (Crawler $cell): string => $cell->text()));
        self::assertSame('/admin/import?month=2026-01', $crawler->filter('[data-test="next-month"]')->attr('href'));
    }

    public function testMonthWithNoDatesShowsAMessage(): void
    {
        $this->loginAs($this->client, 'admin');

        $crawler = $this->client->request('GET', '/admin/import?month=2025-11');

        self::assertSame('Ingen datoer med kørselsdata i måneden.', $crawler->filter('[data-test="no-dates"]')->text());
        self::assertCount(0, $crawler->filter('[data-test="uploaded-dates"]'));
    }

    /**
     * A month that is not valid, or that is after the current month, gives the current month.
     */
    public function testMonthThatIsNotValidGivesTheCurrentMonth(): void
    {
        $this->loginAs($this->client, 'admin');

        foreach (['2026-13', 'foo', '2026-02'] as $month) {
            $crawler = $this->client->request('GET', '/admin/import?month='.$month);

            self::assertResponseIsSuccessful();
            self::assertSame('januar 2026', $crawler->filter('[data-test="month"]')->text(), $month);
        }
    }

    /**
     * The button opens the popover with the counts and the form. No script is necessary.
     */
    public function testClearButtonOpensThePopoverOfTheDate(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2026-01-05');

        $crawler = $this->client->request('GET', '/admin/import');

        $button = $crawler->filter('[data-test="clear-open"]');
        self::assertSame('Ryd', $button->text());
        self::assertSame('Ryd kørselsdata for 5. januar 2026', $button->attr('aria-label'));
        $popover = $crawler->filter('#'.$button->attr('popovertarget'));
        self::assertSame('clear-2026-01-05', $popover->attr('id'));
        self::assertSame('', $popover->attr('popover'));
        self::assertSame('Ryd kørselsdata for 5. januar 2026', $popover->filter('[data-test="clear-title"]')->text());
        self::assertSame('Det sletter 14 stoprækker, 4 vognløb og 0 afviste rækker for datoen og de tal, der er beregnet ud fra dem. Det kan ikke fortrydes.', $popover->filter('[data-test="clear-text"]')->text());
        $cancel = $popover->filter('[data-test="clear-cancel"]');
        self::assertSame('clear-2026-01-05', $cancel->attr('popovertarget'));
        self::assertSame('hide', $cancel->attr('popovertargetaction'));
        // The focus goes into the popover, on the button that changes no data.
        self::assertSame('', $cancel->attr('autofocus'));
    }

    public function testClearDeletesTheDrivingDataOfTheDate(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2025-12-05');
        $this->importBaseOn('2026-01-05');
        $this->importBaseOn('2026-01-06');
        $crawler = $this->client->request('GET', '/admin/import');

        $this->client->submit($crawler->filter('#clear-2026-01-05 form')->form());

        self::assertResponseRedirects('/admin/import?month=2026-01');
        $crawler = $this->client->followRedirect();
        self::assertSame('Kørselsdata for 5. januar 2026 er ryddet. 14 stoprækker, 4 vognløb og 0 afviste rækker er slettet.', $crawler->filter('[data-test="flash-success"]')->text());
        self::assertSame(['6. januar 2026'], $crawler->filter('[data-test="uploaded-date"] td:first-child')->each(static fn (Crawler $cell): string => $cell->text()));
        self::assertSame(28, $this->stored('stop_row'));
        self::assertSame(3, $this->stored('import_batch'));
    }

    /**
     * The flash message goes to the month of the cleared date, also when it is not the current
     * month.
     */
    public function testClearOfADateInAnotherMonthGoesBackToThatMonth(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2025-12-05');
        $crawler = $this->client->request('GET', '/admin/import?month=2025-12');

        $this->client->submit($crawler->filter('#clear-2025-12-05 form')->form());

        self::assertResponseRedirects('/admin/import?month=2025-12');
        self::assertSame(0, $this->stored('stop_row'));
    }

    public function testClearWithNoCsrfTokenIsDenied(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2026-01-05');

        $this->client->request('POST', '/admin/import/clear/2026-01-05');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(14, $this->stored('stop_row'));
    }

    /**
     * The token of one date does not clear another date.
     */
    public function testClearWithTheTokenOfAnotherDateIsDenied(): void
    {
        $this->loginAs($this->client, 'admin');
        $this->importBaseOn('2026-01-05');
        $this->importBaseOn('2026-01-06');
        $crawler = $this->client->request('GET', '/admin/import');
        $token = $crawler->filter('#clear-2026-01-05 input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/import/clear/2026-01-06', ['_token' => $token]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(28, $this->stored('stop_row'));
    }

    public function testUserWithoutTheDrivingDataRoleCannotClear(): void
    {
        $this->loginAs($this->client, 'viewer');
        $this->importBaseOn('2026-01-05');

        $this->client->request('POST', '/admin/import/clear/2026-01-05');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(14, $this->stored('stop_row'));
    }

    public function testClearOfADateThatIsNotValidIsNotFound(): void
    {
        $this->loginAs($this->client, 'admin');

        $this->client->request('POST', '/admin/import/clear/2026-02-30');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Gives the user that loginAs() stored.
     */
    private function user(string $email): User
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->findOneByEmail($email);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function stored(string $table): int
    {
        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    private function connection(): Connection
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager->getConnection();
    }
}

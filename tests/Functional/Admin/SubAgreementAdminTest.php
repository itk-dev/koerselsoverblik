<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Route;
use App\Entity\SubAgreement;
use App\Entity\SubAgreementSupplierAssignment;
use App\Entity\Supplier;
use App\Supplier\SubAgreementSupplierChanged;
use App\Supplier\ValidityPeriod;
use App\Tests\Functional\LoginTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the sub-agreement pages and the supplier assignments.
 */
final class SubAgreementAdminTest extends WebTestCase
{
    use LoginTrait;

    private KernelBrowser $client;

    /**
     * @var list<SubAgreementSupplierChanged>
     */
    private array $events = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // The same kernel for all requests, so that the test listener stays registered.
        $this->client->disableReboot();
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(SubAgreementSupplierChanged::class, function (SubAgreementSupplierChanged $event): void {
            $this->events[] = $event;
        });
        $this->loginAs($this->client, 'admin');
    }

    public function testEmptyList(): void
    {
        $this->client->request('GET', '/admin/sub-agreements');

        self::assertSelectorExists('[data-test="sub-agreement-none"]');
    }

    public function testAdminCanCreateASubAgreement(): void
    {
        $this->client->request('GET', '/admin/sub-agreements/new');
        $this->client->submitForm('Gem', ['sub_agreement[reference]' => ' AB-CD ']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('h1', 'Delaftale AB-CD');
        self::assertSelectorTextContains('[data-test="flash-success"]', 'Delaftalen er oprettet.');
        self::assertSelectorExists('[data-test="assignment-none"]');
        self::assertSelectorExists('[data-test="route-none"]');
    }

    public function testSubAgreementNeedsAUniqueReference(): void
    {
        $this->subAgreement('AB-CD');

        $this->client->request('GET', '/admin/sub-agreements/new');
        $this->client->submitForm('Gem', ['sub_agreement[reference]' => 'AB-CD']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.itkaarhus-field', 'Der findes allerede en delaftale med dette navn.');
    }

    /**
     * A sub-agreement without a supplier today needs an assignment, so the list marks it.
     */
    public function testListShowsTheSupplierOfToday(): void
    {
        $supplier = $this->supplier('Vognmand A');
        $this->assign($this->subAgreement('A-NOW'), $supplier, 'today', null);
        $this->subAgreement('B-NONE');
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', '/admin/sub-agreements');

        $rows = $crawler->filter('[data-test="sub-agreement-row"]');
        self::assertCount(2, $rows);
        self::assertStringContainsString('B-NONE', $rows->eq(0)->text());
        self::assertCount(1, $rows->eq(0)->filter('[data-test="no-current-supplier"]'));
        self::assertStringContainsString('A-NOW', $rows->eq(1)->text());
        self::assertStringContainsString('Vognmand A', $rows->eq(1)->text());
    }

    public function testPageShowsTheRoutesOfTheSubAgreement(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $this->entityManager()->persist(new Route('AB-CD-02', $subAgreement));
        $this->entityManager()->persist(new Route('AB-CD-01', $subAgreement));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $crawler = $this->client->request('GET', '/admin/sub-agreements/'.$subAgreement->getId());

        self::assertSame(['AB-CD-01', 'AB-CD-02'], $crawler->filter('[data-test="route-list"] li')->each(static fn ($item): string => $item->text()));
    }

    public function testAdminCanAssignASupplier(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $supplier = $this->supplier('Vognmand A');
        $this->entityManager()->flush();

        $this->client->request('GET', '/admin/sub-agreements/'.$subAgreement->getId());
        $this->client->clickLink('Tilføj leverandør');
        $this->client->submitForm('Gem', [
            'assignment[supplier]' => (string) $supplier->getId(),
            'assignment[validFrom]' => '2026-01-01',
        ]);
        self::assertResponseRedirects('/admin/sub-agreements/'.$subAgreement->getId());
        $this->client->followRedirect();

        self::assertSelectorTextContains('[data-test="assignment-row"]', 'Vognmand A');
        self::assertSelectorTextContains('[data-test="assignment-row"]', '01.01.2026');
        self::assertSelectorTextContains('[data-test="assignment-row"]', 'Ingen slutdato');
        self::assertCount(1, $this->events);
        self::assertNull($this->events[0]->oldPeriod);
        self::assertEquals(self::period('2026-01-01', null), $this->events[0]->newPeriod);
    }

    public function testChangedAssignmentDispatchesTheOldAndTheNewPeriod(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $assignment = $this->assign($subAgreement, $this->supplier('Vognmand A'), '2026-01-01', null);
        $this->entityManager()->flush();
        // The changed assignment is loaded from the database, and is not compared with itself.
        $this->entityManager()->clear();

        $this->client->request('GET', '/admin/assignments/'.$assignment->getId().'/edit');
        $this->client->submitForm('Gem', ['assignment[validTo]' => '2026-06-30']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('[data-test="assignment-row"]', '30.06.2026');
        self::assertCount(1, $this->events);
        self::assertEquals(self::period('2026-01-01', null), $this->events[0]->oldPeriod);
        self::assertEquals(self::period('2026-01-01', '2026-06-30'), $this->events[0]->newPeriod);
    }

    public function testDeletedAssignmentDispatchesTheOldPeriod(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $this->assign($subAgreement, $this->supplier('Vognmand A'), '2026-01-01', '2026-03-31');
        $this->entityManager()->flush();

        $this->client->request('GET', '/admin/sub-agreements/'.$subAgreement->getId());
        $this->client->submitForm('Slet');
        $this->client->followRedirect();

        self::assertSelectorTextContains('[data-test="flash-success"]', 'Leverandøren for delaftalen er slettet.');
        self::assertSelectorExists('[data-test="assignment-none"]');
        self::assertCount(1, $this->events);
        self::assertEquals(self::period('2026-01-01', '2026-03-31'), $this->events[0]->oldPeriod);
        self::assertNull($this->events[0]->newPeriod);
    }

    public function testOverlappingAssignmentIsRejected(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $supplier = $this->supplier('Vognmand A');
        $this->assign($subAgreement, $supplier, '2026-01-01', null);
        $this->entityManager()->flush();
        // The validator must load the other assignments from the database.
        $this->entityManager()->clear();

        $this->client->request('GET', '/admin/sub-agreements/'.$subAgreement->getId().'/assignments/new');
        $this->client->submitForm('Gem', [
            'assignment[supplier]' => (string) $supplier->getId(),
            'assignment[validFrom]' => '2026-06-01',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'Perioden overlapper en anden periode for delaftalen.');
        self::assertSame([], $this->events);
    }

    public function testLastDateBeforeTheFirstDateIsRejected(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $supplier = $this->supplier('Vognmand A');
        $this->entityManager()->flush();

        $this->client->request('GET', '/admin/sub-agreements/'.$subAgreement->getId().'/assignments/new');
        $this->client->submitForm('Gem', [
            'assignment[supplier]' => (string) $supplier->getId(),
            'assignment[validFrom]' => '2026-06-01',
            'assignment[validTo]' => '2026-05-31',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'Slutdatoen må ikke være før startdatoen.');
    }

    public function testDeleteWithoutAValidTokenIsDenied(): void
    {
        $assignment = $this->assign($this->subAgreement('AB-CD'), $this->supplier('Vognmand A'), '2026-01-01', null);
        $this->entityManager()->flush();

        $this->client->request('POST', '/admin/assignments/'.$assignment->getId().'/delete', ['_token' => 'wrong']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame([], $this->events);
    }

    public function testUnknownSubAgreementIsNotFound(): void
    {
        $this->client->request('GET', '/admin/sub-agreements/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function subAgreement(string $reference): SubAgreement
    {
        $subAgreement = new SubAgreement()->setReference($reference);
        $this->entityManager()->persist($subAgreement);
        $this->entityManager()->flush();

        return $subAgreement;
    }

    private function supplier(string $name): Supplier
    {
        $supplier = new Supplier()->setName($name);
        $this->entityManager()->persist($supplier);

        return $supplier;
    }

    private function assign(SubAgreement $subAgreement, Supplier $supplier, string $from, ?string $to): SubAgreementSupplierAssignment
    {
        $assignment = new SubAgreementSupplierAssignment($subAgreement, $supplier, new \DateTimeImmutable($from), null === $to ? null : new \DateTimeImmutable($to));
        $subAgreement->getAssignments()->add($assignment);
        $this->entityManager()->persist($assignment);

        return $assignment;
    }

    private static function period(string $from, ?string $to): ValidityPeriod
    {
        return new ValidityPeriod(new \DateTimeImmutable($from), null === $to ? null : new \DateTimeImmutable($to));
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}

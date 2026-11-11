<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Route;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Tests\Functional\LoginTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the sub-agreement pages and the supplier of a sub-agreement.
 */
final class SubAgreementAdminTest extends WebTestCase
{
    use LoginTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loginAs($this->client, 'admin');
    }

    public function testEmptyList(): void
    {
        $this->client->request('GET', '/admin/sub-agreements');

        self::assertSelectorExists('[data-test="sub-agreement-none"]');
    }

    public function testAdminCanCreateASubAgreementWithASupplier(): void
    {
        $supplier = $this->supplier('Vognmand A');
        $this->entityManager()->flush();

        $this->client->request('GET', '/admin/sub-agreements/new');
        $this->client->submitForm('Gem', [
            'sub_agreement[reference]' => ' AB-CD ',
            'sub_agreement[supplier]' => (string) $supplier->getId(),
        ]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('h1', 'Delaftale AB-CD');
        self::assertSelectorTextContains('[data-test="flash-success"]', 'Delaftalen er oprettet.');
        self::assertSelectorTextSame('[data-test="supplier"]', 'Vognmand A');
        self::assertSelectorExists('[data-test="route-none"]');
    }

    public function testAdminCanCreateASubAgreementWithNoSupplier(): void
    {
        $this->client->request('GET', '/admin/sub-agreements/new');
        $this->client->submitForm('Gem', ['sub_agreement[reference]' => 'AB-CD']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('[data-test="supplier"]', 'Ingen leverandør');
    }

    public function testSubAgreementNeedsAUniqueReference(): void
    {
        $this->subAgreement('AB-CD');
        $this->entityManager()->flush();

        $this->client->request('GET', '/admin/sub-agreements/new');
        $this->client->submitForm('Gem', ['sub_agreement[reference]' => 'AB-CD']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.itkaarhus-field', 'Der findes allerede en delaftale med dette navn.');
    }

    /**
     * A sub-agreement with no supplier needs a supplier, so the list marks it.
     */
    public function testListShowsTheSupplier(): void
    {
        $this->subAgreement('A-SUPPLIER', $this->supplier('Vognmand A'));
        $this->subAgreement('B-NONE');
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', '/admin/sub-agreements');

        $rows = $crawler->filter('[data-test="sub-agreement-row"]');
        self::assertCount(2, $rows);
        self::assertStringContainsString('B-NONE', $rows->eq(0)->text());
        self::assertCount(1, $rows->eq(0)->filter('[data-test="no-supplier"]'));
        self::assertStringContainsString('A-SUPPLIER', $rows->eq(1)->text());
        self::assertStringContainsString('Vognmand A', $rows->eq(1)->text());
        self::assertCount(0, $rows->eq(1)->filter('[data-test="no-supplier"]'));
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

    public function testAdminCanChangeTheSupplier(): void
    {
        $subAgreement = $this->subAgreement('AB-CD', $this->supplier('Vognmand A'));
        $other = $this->supplier('Vognmand B');
        $this->entityManager()->flush();

        $this->client->request('GET', '/admin/sub-agreements/'.$subAgreement->getId());
        $this->client->click($this->client->getCrawler()->filter('[data-test="sub-agreement-edit"]')->link());
        $this->client->submitForm('Gem', ['sub_agreement[supplier]' => (string) $other->getId()]);
        self::assertResponseRedirects('/admin/sub-agreements/'.$subAgreement->getId());
        $this->client->followRedirect();

        self::assertSelectorTextContains('[data-test="flash-success"]', 'Delaftalen er gemt.');
        self::assertSelectorTextSame('[data-test="supplier"]', 'Vognmand B');
    }

    public function testAdminCanRemoveTheSupplier(): void
    {
        $subAgreement = $this->subAgreement('AB-CD', $this->supplier('Vognmand A'));
        $this->entityManager()->flush();

        $this->client->request('GET', '/admin/sub-agreements/'.$subAgreement->getId().'/edit');
        $this->client->submitForm('Gem', ['sub_agreement[supplier]' => '']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('[data-test="supplier"]', 'Ingen leverandør');
    }

    public function testAdminCanIgnoreASubAgreementAndIncludeItAgain(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $this->entityManager()->flush();

        $this->client->request('GET', '/admin/sub-agreements/'.$subAgreement->getId().'/edit');
        $this->client->submitForm('Gem', ['sub_agreement[ignored]' => true]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test="ignored"]', 'Ignoreret');

        $this->client->request('GET', '/admin/sub-agreements/'.$subAgreement->getId().'/edit');
        $this->client->submitForm('Gem', ['sub_agreement[ignored]' => false]);
        $this->client->followRedirect();
        self::assertSelectorNotExists('[data-test="ignored"]');
    }

    /**
     * An ignored sub-agreement needs no supplier, so the list shows the ignored tag and no
     * warning.
     */
    public function testAdminCanCreateAnIgnoredSubAgreement(): void
    {
        $this->client->request('GET', '/admin/sub-agreements/new');
        $this->client->submitForm('Gem', [
            'sub_agreement[reference]' => 'BOOKED',
            'sub_agreement[ignored]' => true,
        ]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test="ignored"]', 'Ignoreret');
        // The routes are not in the figures, so they do not show as "Ukendt".
        self::assertSelectorTextNotContains('[data-test="supplier"]', 'Ukendt');

        $crawler = $this->client->request('GET', '/admin/sub-agreements');

        $row = $crawler->filter('[data-test="sub-agreement-row"]');
        self::assertCount(1, $row->filter('[data-test="ignored"]'));
        self::assertCount(0, $row->filter('[data-test="no-supplier"]'));
    }

    /**
     * The import finds the sub-agreement of a route by the reference, so the reference does not
     * change after the create.
     */
    public function testEditPageHasNoReferenceField(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', '/admin/sub-agreements/'.$subAgreement->getId().'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'AB-CD');
        self::assertCount(0, $crawler->filter('[name="sub_agreement[reference]"]'));
        self::assertCount(1, $crawler->filter('[name="sub_agreement[supplier]"]'));
    }

    public function testUserWithoutTheSupplierRoleCannotEdit(): void
    {
        $subAgreement = $this->subAgreement('AB-CD');
        $this->entityManager()->flush();
        $this->loginAs($this->client, 'viewer');

        $this->client->request('GET', '/admin/sub-agreements/'.$subAgreement->getId().'/edit');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUnknownSubAgreementIsNotFound(): void
    {
        $this->client->request('GET', '/admin/sub-agreements/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function subAgreement(string $reference, ?Supplier $supplier = null): SubAgreement
    {
        $subAgreement = new SubAgreement()->setReference($reference)->setSupplier($supplier);
        $this->entityManager()->persist($subAgreement);

        return $subAgreement;
    }

    private function supplier(string $name): Supplier
    {
        $supplier = new Supplier()->setName($name);
        $this->entityManager()->persist($supplier);

        return $supplier;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}

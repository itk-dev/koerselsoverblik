<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Tests\Functional\LoginTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the supplier pages and their access.
 */
final class SupplierAdminTest extends WebTestCase
{
    use LoginTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function adminPages(): iterable
    {
        yield 'suppliers' => ['/admin/suppliers'];
        yield 'new supplier' => ['/admin/suppliers/new'];
        yield 'sub-agreements' => ['/admin/sub-agreements'];
        yield 'new sub-agreement' => ['/admin/sub-agreements/new'];
    }

    #[DataProvider('adminPages')]
    public function testAnonymousUserIsSentToTheLogin(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseRedirects('/openidconnect/login/default');
    }

    #[DataProvider('adminPages')]
    public function testUserWithoutTheSupplierRoleIsDenied(string $url): void
    {
        $this->loginAs($this->client, 'viewer');

        $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    #[DataProvider('adminPages')]
    public function testAdminCanGetThePage(string $url): void
    {
        $this->loginAs($this->client, 'admin');

        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    public function testMenuItemGoesToTheSupplierList(): void
    {
        $this->loginAs($this->client, 'admin');

        $crawler = $this->client->request('GET', '/');

        self::assertSame('/admin/suppliers', $crawler->selectLink('Leverandører og delaftaler')->attr('href'));
    }

    public function testEmptyList(): void
    {
        $this->loginAs($this->client, 'admin');

        $this->client->request('GET', '/admin/suppliers');

        self::assertSelectorExists('[data-test="supplier-none"]');
    }

    public function testAdminCanCreateChangeAndDeleteASupplier(): void
    {
        $this->loginAs($this->client, 'admin');

        $this->client->request('GET', '/admin/suppliers/new');
        $this->client->submitForm('Gem', ['supplier[name]' => ' Vognmand A ']);
        self::assertResponseRedirects('/admin/suppliers');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test="flash-success"]', 'Leverandøren er gemt.');
        self::assertSelectorTextSame('[data-test="supplier-row"] td', 'Vognmand A');

        $this->client->clickLink('Redigér');
        $this->client->submitForm('Gem', ['supplier[name]' => 'Vognmand B']);
        $this->client->followRedirect();
        self::assertSelectorTextSame('[data-test="supplier-row"] td', 'Vognmand B');

        $this->client->submitForm('Slet');
        self::assertResponseRedirects('/admin/suppliers');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test="flash-success"]', 'Leverandøren er slettet.');
        self::assertSelectorExists('[data-test="supplier-none"]');
    }

    public function testSupplierNeedsAUniqueName(): void
    {
        $this->supplier('Vognmand A');
        $this->loginAs($this->client, 'admin');

        $this->client->request('GET', '/admin/suppliers/new');
        $this->client->submitForm('Gem', ['supplier[name]' => 'Vognmand A']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.itkaarhus-field', 'Der findes allerede en leverandør med dette navn.');
    }

    public function testSupplierNeedsAName(): void
    {
        $this->loginAs($this->client, 'admin');

        $this->client->request('GET', '/admin/suppliers/new');
        $this->client->submitForm('Gem', ['supplier[name]' => '  ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * A screen reader must announce that the field is not valid, and read the error with it.
     */
    public function testFieldWithAnErrorIsMarkedAndRefersToTheError(): void
    {
        $this->loginAs($this->client, 'admin');

        $this->client->request('GET', '/admin/suppliers/new');
        $crawler = $this->client->submitForm('Gem', ['supplier[name]' => '  ']);

        $field = $crawler->filter('#supplier_name');
        self::assertSame('true', $field->attr('aria-invalid'));
        $errorId = (string) $field->attr('aria-describedby');
        self::assertNotSame('', $errorId);
        self::assertCount(1, $crawler->filter('.itkaarhus-field #'.$errorId));
    }

    /**
     * The figures of the sub-agreements refer to the supplier, so it is not deleted.
     */
    public function testSupplierWithSubAgreementsCannotBeDeleted(): void
    {
        $supplier = $this->supplier('Vognmand A');
        $this->entityManager()->persist(new SubAgreement()->setReference('AB-CD')->setSupplier($supplier));
        $this->entityManager()->flush();
        $this->loginAs($this->client, 'admin');

        $this->client->request('GET', '/admin/suppliers');
        $this->client->submitForm('Slet');
        $this->client->followRedirect();

        self::assertSelectorTextContains('[data-test="flash-error"]', 'Leverandøren kan ikke slettes');
        self::assertSelectorTextSame('[data-test="supplier-row"] td', 'Vognmand A');
    }

    public function testDeleteWithoutAValidTokenIsDenied(): void
    {
        $supplier = $this->supplier('Vognmand A');
        $this->loginAs($this->client, 'admin');

        $this->client->request('POST', '/admin/suppliers/'.$supplier->getId().'/delete', ['_token' => 'wrong']);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function supplier(string $name): Supplier
    {
        $supplier = new Supplier()->setName($name);
        $this->entityManager()->persist($supplier);
        $this->entityManager()->flush();

        return $supplier;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}

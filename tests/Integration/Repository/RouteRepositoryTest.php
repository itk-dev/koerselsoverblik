<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Route;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Checks the routes that the figures count.
 */
final class RouteRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    /**
     * The routes of an ignored sub-agreement are not counted. With a supplier, only the routes of
     * the sub-agreements of that supplier are counted.
     */
    public function testCountedIds(): void
    {
        $supplierA = $this->supplier('Vognmand A');
        $supplierB = $this->supplier('Vognmand B');
        $ofA = $this->route('A', $supplierA);
        $secondOfA = $this->route('A2', $supplierA);
        $ofB = $this->route('B', $supplierB);
        $unknown = $this->route('U', null);
        $this->route('I', $supplierA, ignored: true);
        $this->entityManager->flush();

        self::assertSame(self::ids($ofA, $secondOfA, $ofB, $unknown), $this->sorted($this->repository()->countedIds()));
        self::assertSame(self::ids($ofA, $secondOfA), $this->sorted($this->repository()->countedIds($supplierA->getId())));
        self::assertSame(self::ids($ofB), $this->repository()->countedIds($supplierB->getId()));
    }

    public function testSupplierWithNoRoutesHasNoCountedIds(): void
    {
        $supplier = $this->supplier('Vognmand A');
        $this->route('R', null);
        $this->entityManager->flush();

        self::assertSame([], $this->repository()->countedIds($supplier->getId()));
    }

    private function repository(): RouteRepository
    {
        $routes = $this->entityManager->getRepository(Route::class);
        self::assertInstanceOf(RouteRepository::class, $routes);

        return $routes;
    }

    private function supplier(string $name): Supplier
    {
        $supplier = new Supplier()->setName($name);
        $this->entityManager->persist($supplier);

        return $supplier;
    }

    private function route(string $reference, ?Supplier $supplier, bool $ignored = false): Route
    {
        $subAgreement = new SubAgreement()->setReference($reference)->setIgnored($ignored)->setSupplier($supplier);
        $this->entityManager->persist($subAgreement);
        $route = new Route($reference.'-01', $subAgreement);
        $this->entityManager->persist($route);

        return $route;
    }

    /**
     * @return list<int>
     */
    private static function ids(Route ...$routes): array
    {
        return array_values(array_map(static fn (Route $route): int => (int) $route->getId(), $routes));
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}

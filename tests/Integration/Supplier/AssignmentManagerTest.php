<?php

namespace App\Tests\Integration\Supplier;

use App\Entity\SubAgreement;
use App\Entity\Supplier;
use App\Repository\SubAgreementSupplierAssignmentRepository;
use App\Supplier\AssignmentDto;
use App\Supplier\AssignmentManager;
use App\Supplier\SubAgreementSupplierChanged;
use App\Supplier\ValidityPeriod;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Checks that each change of an assignment is saved and dispatches the event with the old and the
 * new period. The figures are built again for both periods.
 */
final class AssignmentManagerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    /**
     * @var list<SubAgreementSupplierChanged>
     */
    private array $events = [];

    protected function setUp(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(SubAgreementSupplierChanged::class, function (SubAgreementSupplierChanged $event): void {
            $this->events[] = $event;
        });
    }

    public function testNewAssignmentIsSavedAndHasNoOldPeriod(): void
    {
        $data = $this->newData('2026-01-01', null);

        $assignment = $this->manager()->save($data);

        self::assertNotNull($assignment->getId());
        self::assertSame($data->supplier, $assignment->getSupplier());
        self::assertTrue($data->subAgreement->getAssignments()->contains($assignment));
        $event = $this->onlyEvent();
        self::assertSame($data->subAgreement, $event->subAgreement);
        self::assertNull($event->oldPeriod);
        self::assertEquals(self::period('2026-01-01', null), $event->newPeriod);
    }

    public function testChangedAssignmentHasTheOldAndTheNewPeriod(): void
    {
        $assignment = $this->manager()->save($this->newData('2026-01-01', null));
        $this->events = [];
        $other = (new Supplier())->setName('Anden Vognmand');
        $this->entityManager->persist($other);

        $data = AssignmentDto::forChange($assignment);
        $data->supplier = $other;
        $data->validTo = new \DateTimeImmutable('2026-06-30');
        $this->manager()->save($data);

        self::assertSame($other, $assignment->getSupplier());
        $event = $this->onlyEvent();
        self::assertEquals(self::period('2026-01-01', null), $event->oldPeriod);
        self::assertEquals(self::period('2026-01-01', '2026-06-30'), $event->newPeriod);
    }

    public function testDeletedAssignmentHasNoNewPeriod(): void
    {
        $assignment = $this->manager()->save($this->newData('2026-01-01', '2026-03-31'));
        $this->events = [];
        $subAgreement = $assignment->getSubAgreement();

        $this->manager()->remove($assignment);

        $repository = self::getContainer()->get(SubAgreementSupplierAssignmentRepository::class);
        self::assertInstanceOf(SubAgreementSupplierAssignmentRepository::class, $repository);
        self::assertSame(0, $repository->count([]));
        self::assertFalse($subAgreement->getAssignments()->contains($assignment));
        $event = $this->onlyEvent();
        self::assertSame($subAgreement, $event->subAgreement);
        self::assertEquals(self::period('2026-01-01', '2026-03-31'), $event->oldPeriod);
        self::assertNull($event->newPeriod);
    }

    /**
     * The form validates the data first, so this is a bug in the caller.
     */
    public function testDataWithoutAFirstDateIsRejected(): void
    {
        $data = $this->newData('2026-01-01', null);
        $data->validFrom = null;

        $this->expectException(\InvalidArgumentException::class);

        $this->manager()->save($data);
    }

    public function testDataWithoutASupplierIsRejected(): void
    {
        $data = $this->newData('2026-01-01', null);
        $data->supplier = null;

        $this->expectException(\InvalidArgumentException::class);

        $this->manager()->save($data);
    }

    private function onlyEvent(): SubAgreementSupplierChanged
    {
        self::assertCount(1, $this->events);

        return $this->events[0];
    }

    private function manager(): AssignmentManager
    {
        $manager = self::getContainer()->get(AssignmentManager::class);
        self::assertInstanceOf(AssignmentManager::class, $manager);

        return $manager;
    }

    private function newData(string $from, ?string $to): AssignmentDto
    {
        $subAgreement = (new SubAgreement())->setReference('Ø4-VB');
        $supplier = (new Supplier())->setName('Vognmand');
        $this->entityManager->persist($subAgreement);
        $this->entityManager->persist($supplier);

        $data = AssignmentDto::forNew($subAgreement);
        $data->supplier = $supplier;
        $data->validFrom = new \DateTimeImmutable($from);
        $data->validTo = null === $to ? null : new \DateTimeImmutable($to);

        return $data;
    }

    private static function period(string $from, ?string $to): ValidityPeriod
    {
        return new ValidityPeriod(new \DateTimeImmutable($from), null === $to ? null : new \DateTimeImmutable($to));
    }
}

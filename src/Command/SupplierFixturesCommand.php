<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\DrivingData\DevDatasetGenerator;
use App\Entity\SubAgreement;
use App\Entity\Supplier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Gives the sub-agreements of the development dataset their invented suppliers. Run it after the
 * import, because the import creates the sub-agreements.
 */
#[AsCommand(name: 'app:fixtures:suppliers', description: 'Give the sub-agreements of the development dataset their invented suppliers')]
#[When(env: 'dev')]
#[When(env: 'test')]
final readonly class SupplierFixturesCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $suppliers = $this->entityManager->getRepository(Supplier::class);
        $subAgreements = $this->entityManager->getRepository(SubAgreement::class);

        $count = 0;
        foreach (DevDatasetGenerator::SUPPLIERS as $reference => $name) {
            $subAgreement = $subAgreements->findOneBy(['reference' => $reference]);
            if (null === $subAgreement) {
                continue;
            }
            $supplier = $suppliers->findOneBy(['name' => $name]);
            if (null === $supplier) {
                $supplier = new Supplier()->setName($name);
                $this->entityManager->persist($supplier);
                // The next sub-agreement of the same supplier finds it.
                $this->entityManager->flush();
            }
            $subAgreement->setSupplier($supplier);
            ++$count;
        }
        $this->entityManager->flush();

        $io->success(\sprintf('Gave %d sub-agreements a supplier.', $count));

        return Command::SUCCESS;
    }
}

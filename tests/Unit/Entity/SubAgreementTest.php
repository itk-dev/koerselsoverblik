<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\SubAgreement;
use App\Entity\Supplier;
use PHPUnit\Framework\TestCase;

final class SubAgreementTest extends TestCase
{
    public function testNewSubAgreementHasNoSupplier(): void
    {
        self::assertNull(new SubAgreement()->getSupplier());
    }

    public function testSupplierCanBeSetAndRemoved(): void
    {
        $supplier = new Supplier()->setName('Vognmand A');
        $subAgreement = new SubAgreement();

        self::assertSame($subAgreement, $subAgreement->setSupplier($supplier));
        self::assertSame($supplier, $subAgreement->getSupplier());

        $subAgreement->setSupplier(null);
        self::assertNull($subAgreement->getSupplier());
    }

    public function testNewSubAgreementIsNotIgnored(): void
    {
        self::assertFalse(new SubAgreement()->isIgnored());
    }

    public function testIgnoredFlagCanBeSetAndCleared(): void
    {
        $subAgreement = new SubAgreement();

        self::assertSame($subAgreement, $subAgreement->setIgnored(true));
        self::assertTrue($subAgreement->isIgnored());

        $subAgreement->setIgnored(false);
        self::assertFalse($subAgreement->isIgnored());
    }
}

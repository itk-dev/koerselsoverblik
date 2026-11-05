<?php

namespace App\Tests\Unit\Doctrine\DBAL\Types;

use App\Doctrine\DBAL\Types\UTCDateTimeImmutableType;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UTCDateTimeImmutableTypeTest extends TestCase
{
    private string $defaultTimeZone;

    protected function setUp(): void
    {
        $this->defaultTimeZone = date_default_timezone_get();
        // The type must not depend on the PHP default time zone.
        date_default_timezone_set('Europe/Copenhagen');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimeZone);
    }

    public function testAValueIsWrittenInUtc(): void
    {
        $value = new \DateTimeImmutable('2026-10-28 13:00:00', new \DateTimeZone('Europe/Copenhagen'));

        self::assertSame('2026-10-28 12:00:00', (new UTCDateTimeImmutableType())->convertToDatabaseValue($value, new MariaDBPlatform()));
    }

    #[DataProvider('nullProvider')]
    public function testNullIsWrittenAndReadAsNull(mixed $value): void
    {
        self::assertNull((new UTCDateTimeImmutableType())->convertToDatabaseValue($value, new MariaDBPlatform()));
        self::assertNull((new UTCDateTimeImmutableType())->convertToPHPValue($value, new MariaDBPlatform()));
    }

    /**
     * The value comes from a provider, so that PHPStan does not know that it is null.
     *
     * @return iterable<string, array{mixed}>
     */
    public static function nullProvider(): iterable
    {
        yield 'null' => [null];
    }

    public function testAValueIsReadInUtc(): void
    {
        $value = (new UTCDateTimeImmutableType())->convertToPHPValue('2026-10-28 12:00:00', new MariaDBPlatform());

        self::assertInstanceOf(\DateTimeImmutable::class, $value);
        self::assertSame('2026-10-28 12:00:00 UTC', $value->format('Y-m-d H:i:s T'));
    }

    public function testAnObjectIsReadAsItIs(): void
    {
        $value = new \DateTimeImmutable('2026-10-28 13:00:00', new \DateTimeZone('Europe/Copenhagen'));

        self::assertSame($value, (new UTCDateTimeImmutableType())->convertToPHPValue($value, new MariaDBPlatform()));
    }

    public function testAValueWithAnotherFormatIsRejected(): void
    {
        $this->expectException(InvalidFormat::class);

        (new UTCDateTimeImmutableType())->convertToPHPValue('28.10.2026 12:00', new MariaDBPlatform());
    }
}

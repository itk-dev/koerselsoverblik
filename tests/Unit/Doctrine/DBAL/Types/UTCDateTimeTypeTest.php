<?php

namespace App\Tests\Unit\Doctrine\DBAL\Types;

use App\Doctrine\DBAL\Types\UTCDateTimeType;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UTCDateTimeTypeTest extends TestCase
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

    public function testAValueIsWrittenInUtcAndTheEntityValueDoesNotChange(): void
    {
        $value = new \DateTime('2026-10-28 13:00:00', new \DateTimeZone('Europe/Copenhagen'));

        self::assertSame('2026-10-28 12:00:00', (new UTCDateTimeType())->convertToDatabaseValue($value, new MariaDBPlatform()));
        self::assertSame('2026-10-28 13:00:00 Europe/Copenhagen', $value->format('Y-m-d H:i:s e'));
    }

    #[DataProvider('nullProvider')]
    public function testNullIsWrittenAndReadAsNull(mixed $value): void
    {
        self::assertNull((new UTCDateTimeType())->convertToDatabaseValue($value, new MariaDBPlatform()));
        self::assertNull((new UTCDateTimeType())->convertToPHPValue($value, new MariaDBPlatform()));
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
        $value = (new UTCDateTimeType())->convertToPHPValue('2026-10-28 12:00:00', new MariaDBPlatform());

        self::assertInstanceOf(\DateTime::class, $value);
        self::assertSame('2026-10-28 12:00:00 UTC', $value->format('Y-m-d H:i:s T'));
    }

    public function testAnObjectIsReadAsItIs(): void
    {
        $value = new \DateTime('2026-10-28 13:00:00', new \DateTimeZone('Europe/Copenhagen'));

        self::assertSame($value, (new UTCDateTimeType())->convertToPHPValue($value, new MariaDBPlatform()));
    }

    public function testAValueWithAnotherFormatIsRejected(): void
    {
        $this->expectException(InvalidFormat::class);

        (new UTCDateTimeType())->convertToPHPValue('28.10.2026 12:00', new MariaDBPlatform());
    }
}

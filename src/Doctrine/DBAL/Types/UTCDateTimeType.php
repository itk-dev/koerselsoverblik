<?php

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;

/**
 * Writes a date and time in UTC, and reads it back in UTC. The PHP default time zone has no
 * effect. See ADR 005.
 */
final class UTCDateTimeType extends DateTimeType
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTime) {
            // \DateTime is mutable, and the entity owns the object. Convert a copy.
            $value = (clone $value)->setTimezone(new \DateTimeZone('UTC'));
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTime
    {
        // The parent type gives null and an object as they are.
        if (!\is_string($value)) {
            return parent::convertToPHPValue($value, $platform);
        }

        $converted = \DateTime::createFromFormat($platform->getDateTimeFormatString(), $value, new \DateTimeZone('UTC'));
        if (false === $converted) {
            throw InvalidFormat::new($value, static::class, $platform->getDateTimeFormatString());
        }

        return $converted;
    }
}

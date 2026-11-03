<?php

namespace App\Import\Extract;

use App\Import\Mapping\FieldMappingDto;
use App\Import\Mapping\FieldType;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\ErrorCell;

/**
 * Converts the cell of one field to an application value, by the type of the field.
 */
final readonly class CellConverter
{
    /**
     * Day 25 569 in Excel is 1 January 1970, the start of a Unix timestamp.
     */
    private const int EXCEL_UNIX_START = 25569;

    private const int SECONDS_IN_A_DAY = 86400;

    /**
     * Gives null for an empty cell. The parser decides if the field must have a value.
     *
     * A "time" field gives a DateInterval. StopRowDto adds it to the service date.
     *
     * @throws RejectedValueException if the value does not agree with the field
     */
    public function convert(FieldMappingDto $field, ?Cell $cell): string|int|float|bool|\DateTimeImmutable|\DateInterval|null
    {
        if ($cell instanceof ErrorCell) {
            self::reject(RejectionReason::ErrorValue);
        }

        $value = $cell?->getValue();
        if (\is_string($value)) {
            $value = trim($value);
        }
        if (null === $value || '' === $value) {
            return null;
        }

        return match ($field->type) {
            FieldType::String => self::text($field, $value),
            FieldType::Int => \is_int($value) ? $value : self::reject(RejectionReason::NotWholeNumber),
            FieldType::Float => \is_int($value) || \is_float($value) ? (float) $value : self::reject(RejectionReason::NotNumber),
            FieldType::Bool => self::bool($field, $value),
            FieldType::Date => self::dateTime($field, $value) ?? self::reject(RejectionReason::NotDate),
            FieldType::DateTime => self::dateTime($field, $value) ?? self::reject(RejectionReason::NotDateTime),
            FieldType::Time => $value instanceof \DateInterval ? $value : self::reject(RejectionReason::NotTime),
        };
    }

    /**
     * A whole number is also text, for example a route name such as 101.
     */
    private static function text(FieldMappingDto $field, mixed $value): string
    {
        $text = \is_string($value) || \is_int($value) ? (string) $value : self::reject(RejectionReason::NotText);
        if ([] === $field->valueMap) {
            return $text;
        }

        return $field->valueMap[$text] ?? self::reject(RejectionReason::UnknownCode);
    }

    private static function bool(FieldMappingDto $field, mixed $value): bool
    {
        $text = \is_string($value) || \is_int($value) ? (string) $value : null;

        return match (true) {
            \in_array($text, $field->trueValues, true) => true,
            \in_array($text, $field->falseValues, true) => false,
            default => self::reject(RejectionReason::UnknownBoolValue),
        };
    }

    /**
     * A field with a format string reads text. Other fields read a date cell or an Excel serial
     * number.
     */
    private static function dateTime(FieldMappingDto $field, mixed $value): ?\DateTimeImmutable
    {
        if (null !== $field->format && FieldMappingDto::EXCEL_FORMAT !== $field->format) {
            return \is_string($value) ? self::fromFormat($field->format, $value) : null;
        }

        return match (true) {
            $value instanceof \DateTimeInterface => \DateTimeImmutable::createFromInterface($value),
            \is_int($value) || \is_float($value) => self::fromExcelSerial($value),
            default => null,
        };
    }

    private static function fromFormat(string $format, string $value): ?\DateTimeImmutable
    {
        // "!" sets the fields that the format does not give to 0, for example the time of a date.
        $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

        // Text that does not agree with the format gives an error. A date that does not exist, for
        // example 30 February, gives a warning.
        return false !== $date && false === \DateTimeImmutable::getLastErrors() ? $date : null;
    }

    /**
     * The time in the extract is a wall-clock time. UTC has no change to summer time, so the
     * conversion uses UTC, and then gives the same wall-clock time in the default time zone.
     */
    private static function fromExcelSerial(int|float $serial): \DateTimeImmutable
    {
        $seconds = round(($serial - self::EXCEL_UNIX_START) * self::SECONDS_IN_A_DAY);
        $utc = new \DateTimeImmutable('@'.\sprintf('%d', $seconds));

        return new \DateTimeImmutable($utc->format('Y-m-d H:i:s'));
    }

    private static function reject(RejectionReason $reason): never
    {
        throw new RejectedValueException($reason);
    }
}

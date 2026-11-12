<?php

declare(strict_types=1);

namespace App\Import\Mapping;

/**
 * The application fields that a mapping reads from an extract.
 */
enum FieldName: string
{
    case Route = 'route';
    case PersonId = 'person_id';
    case ServiceDate = 'service_date';
    case Run = 'run';
    case StopType = 'stop_type';
    case StopStatus = 'stop_status';
    case PassengersAfter = 'passengers_after';
    case NextDistanceKm = 'next_distance_km';
    case NextDurationMin = 'next_duration_min';
    case PlannedTime = 'planned_time';
    case ActualTime = 'actual_time';
    case PlannedSolo = 'planned_solo';
    case Passengers = 'passengers';
    case PricePerMinute = 'price_per_minute';
    case CancellationNoticeMin = 'cancellation_notice_min';
    case CancellationInTime = 'cancellation_in_time';
    case LocationName = 'location_name';

    /**
     * Gives the types that a mapping can give this field. The parser gives one PHP type for each
     * field, so a field cannot have every type.
     *
     * @return non-empty-list<FieldType>
     */
    public function allowedTypes(): array
    {
        return match ($this) {
            self::Route, self::PersonId, self::StopType, self::StopStatus, self::LocationName => [FieldType::String],
            self::ServiceDate => [FieldType::Date, FieldType::DateTime],
            self::Run, self::PassengersAfter, self::Passengers => [FieldType::Int],
            self::NextDistanceKm, self::NextDurationMin, self::PricePerMinute, self::CancellationNoticeMin => [FieldType::Float],
            self::PlannedTime, self::ActualTime => [FieldType::Time, FieldType::DateTime],
            self::PlannedSolo, self::CancellationInTime => [FieldType::Bool],
        };
    }

    /**
     * Tells if a mapping must make this field required. The calculations need these fields in
     * each stop row.
     */
    public function mustBeRequired(): bool
    {
        return match ($this) {
            self::Route, self::ServiceDate, self::Run, self::StopType, self::StopStatus, self::PlannedTime => true,
            default => false,
        };
    }

    /**
     * Gives the enum of the values that the map of this field can give, or null if any value is
     * allowed.
     *
     * @return class-string<StopType|StopStatus>|null
     */
    public function valueEnum(): ?string
    {
        return match ($this) {
            self::StopType => StopType::class,
            self::StopStatus => StopStatus::class,
            default => null,
        };
    }
}

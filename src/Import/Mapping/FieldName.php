<?php

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
}

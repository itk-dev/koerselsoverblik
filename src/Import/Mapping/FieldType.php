<?php

declare(strict_types=1);

namespace App\Import\Mapping;

/**
 * How the parser converts the cell value of a field.
 */
enum FieldType: string
{
    case String = 'string';
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case Date = 'date';
    case Time = 'time';
    case DateTime = 'datetime';
}

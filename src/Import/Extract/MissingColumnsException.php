<?php

declare(strict_types=1);

namespace App\Import\Extract;

/**
 * The extract does not have all the columns of the mapping.
 */
final class MissingColumnsException extends InvalidExtractException
{
    /**
     * @param list<string> $columns the missing column names, from the mapping
     */
    public function __construct(public readonly array $columns)
    {
        parent::__construct(\sprintf('The extract has no columns "%s".', implode('", "', $columns)));
    }
}

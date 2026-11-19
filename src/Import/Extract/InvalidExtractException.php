<?php

declare(strict_types=1);

namespace App\Import\Extract;

/**
 * The parser cannot read an extract, for example because a column is missing. No row of the
 * file is imported.
 *
 * The message can have the names from the mapping. It never has a value from the extract.
 */
class InvalidExtractException extends \RuntimeException
{
    /**
     * @param ?int    $limit   the limit, the header row or the sheet index of the problem
     * @param ?string $message the message, or null for the message of the problem
     */
    public function __construct(
        public readonly ExtractProblem $problem,
        public readonly ?int $limit = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?? $problem->message($limit));
    }
}

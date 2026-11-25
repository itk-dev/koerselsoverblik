<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use Doctrine\DBAL\Connection;

/**
 * Runs one SQL statement after the first flush. A test uses it to change data while a service
 * works, as another change at the same time would.
 */
final class StatementAfterFirstFlush
{
    private bool $done = false;

    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $sql,
        private readonly array $parameters = [],
    ) {
    }

    public function postFlush(): void
    {
        if ($this->done) {
            return;
        }
        $this->done = true;
        $this->connection->executeStatement($this->sql, $this->parameters);
    }
}

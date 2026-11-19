<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

/**
 * Counts the flushes of the entity manager, so that a test can check that the rows are written
 * in groups.
 */
final class FlushCounter
{
    public int $count = 0;

    public function postFlush(): void
    {
        ++$this->count;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Migrations;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * MariaDB commits a transaction before each DDL statement. A migration with DDL must say that it
 * is not transactional, or doctrine/migrations reports a deprecation. A migration that changes only
 * data keeps the default transaction, so that a failure rolls back all its statements.
 */
final class MigrationTransactionTest extends TestCase
{
    private const string DDL = '/addSql\(\s*[\'"]\s*(CREATE|ALTER|DROP|RENAME|TRUNCATE)\b/i';

    private const string DML = '/addSql\(\s*[\'"]\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i';

    private const string NOT_TRANSACTIONAL = '/function isTransactional\(\): bool\s*\{\s*return false;\s*\}/';

    /**
     * @return iterable<string, array{string}>
     */
    public static function migrations(): iterable
    {
        foreach (glob(\dirname(__DIR__, 3).'/migrations/Version*.php') ?: [] as $path) {
            yield basename($path) => [$path];
        }
    }

    #[DataProvider('migrations')]
    public function testOnlyAMigrationWithDdlIsNotTransactional(string $path): void
    {
        $code = (string) file_get_contents($path);
        $hasDdl = 1 === preg_match(self::DDL, $code);

        self::assertSame($hasDdl, 1 === preg_match(self::NOT_TRANSACTIONAL, $code), $hasDdl
            ? 'A migration with DDL must override isTransactional() and return false.'
            : 'A migration with no DDL must keep the default transaction.');
    }

    /**
     * A migration with DDL runs with no transaction, so a failure cannot roll back its data changes.
     * Put the data changes in a separate migration.
     */
    #[DataProvider('migrations')]
    public function testMigrationWithDdlChangesNoData(string $path): void
    {
        $code = (string) file_get_contents($path);

        self::assertFalse(1 === preg_match(self::DDL, $code) && 1 === preg_match(self::DML, $code), 'Put the data changes in a separate migration.');
    }
}

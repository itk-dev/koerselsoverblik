<?php

namespace App\Tests\Integration\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261105151612;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Doctrine loads the migrations from their directory. Composer does not autoload them.
require_once \dirname(__DIR__, 3).'/migrations/Version20261105151612.php';

/**
 * Checks that the migration converts the stored login times from Copenhagen time to UTC, and back.
 */
final class LastLoginAtToUtcTest extends KernelTestCase
{
    public function testUpConvertsCopenhagenTimeToUtc(): void
    {
        $this->insertUsers();

        $this->migrate('up');

        self::assertSame([
            'winter@example.com' => '2026-01-05 06:10:00',
            'summer@example.com' => '2026-06-05 05:10:00',
            'never@example.com' => null,
        ], $this->lastLoginTimes());
    }

    public function testDownGivesTheOldValues(): void
    {
        $this->insertUsers();
        $this->migrate('up');

        $this->migrate('down');

        self::assertSame([
            'winter@example.com' => '2026-01-05 07:10:00',
            'summer@example.com' => '2026-06-05 07:10:00',
            'never@example.com' => null,
        ], $this->lastLoginTimes());
    }

    private function insertUsers(): void
    {
        foreach (['winter@example.com' => '2026-01-05 07:10:00', 'summer@example.com' => '2026-06-05 07:10:00', 'never@example.com' => null] as $email => $time) {
            $this->connection()->insert('`user`', ['email' => $email, 'roles' => '[]', 'last_login_at' => $time]);
        }
    }

    /**
     * Runs the SQL that the migration queues, on the connection of the test transaction. The
     * migration uses positional parameters with no types.
     *
     * @param 'up'|'down' $direction
     */
    private function migrate(string $direction): void
    {
        $migration = new Version20261105151612($this->connection(), new NullLogger());
        $migration->{$direction}(new Schema());

        foreach ($migration->getSql() as $query) {
            $this->connection()->executeStatement($query->getStatement(), array_values($query->getParameters()));
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function lastLoginTimes(): array
    {
        /** @var array<string, string|null> $times */
        $times = $this->connection()->fetchAllKeyValue('SELECT email, last_login_at FROM `user` ORDER BY id');

        return $times;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}

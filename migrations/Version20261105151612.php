<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Converts the stored login times from Copenhagen time to UTC. See ADR 005.
 *
 * The conversion is in PHP, because CONVERT_TZ() gives NULL if the database has no time zone
 * tables. Then the login times are lost.
 */
final class Version20261105151612 extends AbstractMigration
{
    /**
     * The PHP default time zone of the application before this migration.
     */
    private const string OLD_TIME_ZONE = 'Europe/Copenhagen';

    public function getDescription(): string
    {
        return 'Convert the stored login times from Copenhagen time to UTC';
    }

    public function up(Schema $schema): void
    {
        $this->convertLastLoginAt(self::OLD_TIME_ZONE, 'UTC');
    }

    public function down(Schema $schema): void
    {
        $this->convertLastLoginAt('UTC', self::OLD_TIME_ZONE);
    }

    private function convertLastLoginAt(string $from, string $to): void
    {
        $rows = $this->connection->fetchAllKeyValue('SELECT id, last_login_at FROM `user` WHERE last_login_at IS NOT NULL');
        foreach ($rows as $id => $lastLoginAt) {
            $converted = (new \DateTimeImmutable($lastLoginAt, new \DateTimeZone($from)))
                ->setTimezone(new \DateTimeZone($to))
                ->format('Y-m-d H:i:s');
            $this->addSql('UPDATE `user` SET last_login_at = ? WHERE id = ?', [$converted, $id]);
        }
    }
}

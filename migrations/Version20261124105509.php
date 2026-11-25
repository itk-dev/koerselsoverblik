<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124105509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the runs that are not cancelled to the totals of each route and day. Run app:driving-data:rebuild-totals after the migration.';
    }

    /**
     * MariaDB commits before each DDL statement, so a transaction has no effect.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // The existing rows get 0 until the rebuild of the totals.
        $this->addSql('ALTER TABLE route_day_totals ADD not_cancelled_runs INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_day_totals DROP not_cancelled_runs');
    }
}

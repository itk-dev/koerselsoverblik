<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124130636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the code of a late cancellation to the stop rows, and the cancellations in time and too late to the totals of each route and day. Import the stored dates again after the migration.';
    }

    public function up(Schema $schema): void
    {
        // The stored stop rows get no code, and the totals get 0, until the next import.
        $this->addSql('ALTER TABLE route_day_totals ADD cancelled_in_time_trips INT NOT NULL, ADD cancelled_late_trips INT NOT NULL');
        $this->addSql('ALTER TABLE stop_row ADD cancelled_in_time TINYINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_day_totals DROP cancelled_in_time_trips, DROP cancelled_late_trips');
        $this->addSql('ALTER TABLE stop_row DROP cancelled_in_time');
    }
}

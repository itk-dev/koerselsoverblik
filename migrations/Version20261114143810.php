<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114143810 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the totals of each route and day, for the figures of the report pages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE route_day_totals (id INT AUTO_INCREMENT NOT NULL, pickup_on_time INT NOT NULL, pickup_measured INT NOT NULL, drop_off_on_time INT NOT NULL, drop_off_measured INT NOT NULL, first_pickup_on_time INT NOT NULL, first_pickup_measured INT NOT NULL, last_drop_on_time INT NOT NULL, last_drop_measured INT NOT NULL, last_drop_up_to15 INT NOT NULL, last_drop_up_to30 INT NOT NULL, last_drop_over30 INT NOT NULL, trips INT NOT NULL, completed_trips INT NOT NULL, cancelled_trips INT NOT NULL, pending_trips INT NOT NULL, runs INT NOT NULL, solo_runs INT NOT NULL, service_date DATE NOT NULL, tolerance_minutes INT NOT NULL, route_id INT NOT NULL, INDEX IDX_3EB6BD2364DD8396 (service_date), UNIQUE INDEX UNIQ_3EB6BD2334ECB4E664DD8396 (route_id, service_date), INDEX IDX_3EB6BD2334ECB4E6 (route_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE route_day_totals ADD CONSTRAINT FK_3EB6BD2334ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_day_totals DROP FOREIGN KEY FK_3EB6BD2334ECB4E6');
        $this->addSql('DROP TABLE route_day_totals');
    }
}

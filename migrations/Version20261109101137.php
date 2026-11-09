<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109101137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the route runs, and the delay of each stop row in seconds';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE route_run (id INT AUTO_INCREMENT NOT NULL, first_pickup_planned DATETIME DEFAULT NULL, first_pickup_actual DATETIME DEFAULT NULL, first_pickup_delay_seconds INT DEFAULT NULL, last_drop_planned DATETIME DEFAULT NULL, last_drop_actual DATETIME DEFAULT NULL, last_drop_delay_seconds INT DEFAULT NULL, cancelled TINYINT NOT NULL, service_date DATE NOT NULL, run INT NOT NULL, route_id INT NOT NULL, INDEX IDX_72FF67AA64DD8396 (service_date), UNIQUE INDEX UNIQ_72FF67AA34ECB4E664DD83965076A4C0 (route_id, service_date, run), INDEX IDX_72FF67AA34ECB4E6 (route_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE route_run ADD CONSTRAINT FK_72FF67AA34ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id)');
        $this->addSql('ALTER TABLE stop_row ADD delay_seconds INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_run DROP FOREIGN KEY FK_72FF67AA34ECB4E6');
        $this->addSql('DROP TABLE route_run');
        $this->addSql('ALTER TABLE stop_row DROP delay_seconds');
    }
}

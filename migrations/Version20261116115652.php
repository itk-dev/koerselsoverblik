<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116115652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the measured stops of each location, route and day, for the worst locations of the punctuality page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE location_day_totals (id INT AUTO_INCREMENT NOT NULL, on_time INT NOT NULL, measured INT NOT NULL, service_date DATE NOT NULL, location_name VARCHAR(255) NOT NULL, tolerance_minutes INT NOT NULL, route_id INT NOT NULL, INDEX IDX_89E9761B64DD8396 (service_date), UNIQUE INDEX UNIQ_89E9761B34ECB4E664DD8396DE27CB46 (route_id, service_date, location_name), INDEX IDX_89E9761B34ECB4E6 (route_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE location_day_totals ADD CONSTRAINT FK_89E9761B34ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE location_day_totals DROP FOREIGN KEY FK_89E9761B34ECB4E6');
        $this->addSql('DROP TABLE location_day_totals');
    }
}

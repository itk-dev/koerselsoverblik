<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115071127 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the citizens of each month, for all suppliers and for each supplier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE month_citizens (id INT AUTO_INCREMENT NOT NULL, first_date DATE NOT NULL, citizens INT NOT NULL, supplier_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_1F6B06835D6C884E2ADD6D8C (first_date, supplier_id), INDEX IDX_1F6B06832ADD6D8C (supplier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE month_citizens ADD CONSTRAINT FK_1F6B06832ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE month_citizens DROP FOREIGN KEY FK_1F6B06832ADD6D8C');
        $this->addSql('DROP TABLE month_citizens');
    }
}

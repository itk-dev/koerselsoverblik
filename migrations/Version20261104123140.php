<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104123140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add suppliers, sub-agreements, routes and the dated supplier assignments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE route (id INT AUTO_INCREMENT NOT NULL, source_key VARCHAR(255) NOT NULL, sub_agreement_id INT NOT NULL, UNIQUE INDEX UNIQ_2C42079DB64AEE8 (source_key), INDEX IDX_2C4207933DB4C1D (sub_agreement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sub_agreement (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_3C681C9AAEA34913 (reference), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sub_agreement_supplier_assignment (id INT AUTO_INCREMENT NOT NULL, valid_from DATE NOT NULL, valid_to DATE DEFAULT NULL, supplier_id INT NOT NULL, sub_agreement_id INT NOT NULL, INDEX IDX_5E9165737BD7DFB68C3213D0 (valid_from, valid_to), INDEX IDX_5E9165732ADD6D8C (supplier_id), INDEX IDX_5E91657333DB4C1D (sub_agreement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE supplier (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_9B2A6C7E5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE route ADD CONSTRAINT FK_2C4207933DB4C1D FOREIGN KEY (sub_agreement_id) REFERENCES sub_agreement (id)');
        $this->addSql('ALTER TABLE sub_agreement_supplier_assignment ADD CONSTRAINT FK_5E9165732ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id)');
        $this->addSql('ALTER TABLE sub_agreement_supplier_assignment ADD CONSTRAINT FK_5E91657333DB4C1D FOREIGN KEY (sub_agreement_id) REFERENCES sub_agreement (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route DROP FOREIGN KEY FK_2C4207933DB4C1D');
        $this->addSql('ALTER TABLE sub_agreement_supplier_assignment DROP FOREIGN KEY FK_5E9165732ADD6D8C');
        $this->addSql('ALTER TABLE sub_agreement_supplier_assignment DROP FOREIGN KEY FK_5E91657333DB4C1D');
        $this->addSql('DROP TABLE route');
        $this->addSql('DROP TABLE sub_agreement');
        $this->addSql('DROP TABLE sub_agreement_supplier_assignment');
        $this->addSql('DROP TABLE supplier');
    }
}

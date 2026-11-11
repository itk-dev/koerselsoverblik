<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111121826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give each sub-agreement one supplier, and remove the dated assignments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sub_agreement_supplier_assignment DROP FOREIGN KEY `FK_5E9165732ADD6D8C`');
        $this->addSql('ALTER TABLE sub_agreement_supplier_assignment DROP FOREIGN KEY `FK_5E91657333DB4C1D`');
        $this->addSql('DROP TABLE sub_agreement_supplier_assignment');
        $this->addSql('ALTER TABLE sub_agreement ADD supplier_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sub_agreement ADD CONSTRAINT FK_3C681C9A2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id)');
        $this->addSql('CREATE INDEX IDX_3C681C9A2ADD6D8C ON sub_agreement (supplier_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sub_agreement_supplier_assignment (id INT AUTO_INCREMENT NOT NULL, valid_from DATE NOT NULL, valid_to DATE DEFAULT NULL, sub_agreement_id INT NOT NULL, supplier_id INT NOT NULL, INDEX IDX_5E9165732ADD6D8C (supplier_id), INDEX IDX_5E91657333DB4C1D (sub_agreement_id), UNIQUE INDEX UNIQ_5E91657333DB4C1D7BD7DFB6 (sub_agreement_id, valid_from), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE sub_agreement_supplier_assignment ADD CONSTRAINT `FK_5E9165732ADD6D8C` FOREIGN KEY (supplier_id) REFERENCES supplier (id)');
        $this->addSql('ALTER TABLE sub_agreement_supplier_assignment ADD CONSTRAINT `FK_5E91657333DB4C1D` FOREIGN KEY (sub_agreement_id) REFERENCES sub_agreement (id)');
        $this->addSql('ALTER TABLE sub_agreement DROP FOREIGN KEY FK_3C681C9A2ADD6D8C');
        $this->addSql('DROP INDEX IDX_3C681C9A2ADD6D8C ON sub_agreement');
        $this->addSql('ALTER TABLE sub_agreement DROP supplier_id');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The migration does not copy the stored JSON, because the application is not live. A database
 * with driving data cannot get the non-null foreign key. Delete the driving data first.
 */
final class Version20261112065544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move the copy of each imported row to its own table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE source_row (id INT AUTO_INCREMENT NOT NULL, source_values JSON NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE rejected_stop_row ADD source_row_id INT NOT NULL, DROP source_values');
        $this->addSql('ALTER TABLE rejected_stop_row ADD CONSTRAINT FK_5B635D3355D4C14 FOREIGN KEY (source_row_id) REFERENCES source_row (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5B635D3355D4C14 ON rejected_stop_row (source_row_id)');
        $this->addSql('ALTER TABLE stop_row ADD source_row_id INT NOT NULL, DROP source_values');
        $this->addSql('ALTER TABLE stop_row ADD CONSTRAINT FK_B7B5D05F355D4C14 FOREIGN KEY (source_row_id) REFERENCES source_row (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B7B5D05F355D4C14 ON stop_row (source_row_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rejected_stop_row DROP FOREIGN KEY FK_5B635D3355D4C14');
        $this->addSql('DROP INDEX UNIQ_5B635D3355D4C14 ON rejected_stop_row');
        $this->addSql('ALTER TABLE rejected_stop_row ADD source_values JSON NOT NULL, DROP source_row_id');
        $this->addSql('ALTER TABLE stop_row DROP FOREIGN KEY FK_B7B5D05F355D4C14');
        $this->addSql('DROP INDEX UNIQ_B7B5D05F355D4C14 ON stop_row');
        $this->addSql('ALTER TABLE stop_row ADD source_values JSON NOT NULL, DROP source_row_id');
        $this->addSql('DROP TABLE source_row');
    }
}

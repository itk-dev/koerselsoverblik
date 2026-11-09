<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109082331 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the import batches, the stop rows and the rejected rows, each row with its copy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE import_batch (id INT AUTO_INCREMENT NOT NULL, first_date DATE NOT NULL, last_date DATE NOT NULL, rows_deleted INT NOT NULL, rows_inserted INT NOT NULL, rows_rejected INT NOT NULL, warning_count INT NOT NULL, errors JSON NOT NULL, warnings JSON NOT NULL, mapping_name VARCHAR(64) NOT NULL, file_name VARCHAR(255) NOT NULL, uploaded_at DATETIME NOT NULL, uploaded_by_id INT DEFAULT NULL, INDEX IDX_155EFD1AA2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rejected_stop_row (id INT AUTO_INCREMENT NOT NULL, sheet_row_number INT NOT NULL, reason VARCHAR(32) NOT NULL, field VARCHAR(32) DEFAULT NULL, service_date DATE DEFAULT NULL, source_values JSON NOT NULL, import_batch_id INT NOT NULL, INDEX IDX_5B635D364DD8396 (service_date), INDEX IDX_5B635D35A310080 (import_batch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stop_row (id INT AUTO_INCREMENT NOT NULL, row_key VARCHAR(255) DEFAULT NULL, service_date DATE NOT NULL, run INT NOT NULL, stop_type VARCHAR(16) NOT NULL, stop_status VARCHAR(16) NOT NULL, passengers_after INT DEFAULT NULL, next_distance_km DOUBLE PRECISION DEFAULT NULL, next_duration_min DOUBLE PRECISION DEFAULT NULL, planned_time DATETIME NOT NULL, actual_time DATETIME DEFAULT NULL, planned_solo TINYINT DEFAULT NULL, person_id VARCHAR(255) DEFAULT NULL, source_values JSON NOT NULL, mapping_name VARCHAR(64) NOT NULL, route_id INT NOT NULL, import_batch_id INT NOT NULL, INDEX IDX_B7B5D05F64DD8396 (service_date), UNIQUE INDEX UNIQ_B7B5D05F9E60EEF6B274F263 (mapping_name, row_key), INDEX IDX_B7B5D05F34ECB4E6 (route_id), INDEX IDX_B7B5D05F5A310080 (import_batch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE import_batch ADD CONSTRAINT FK_155EFD1AA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rejected_stop_row ADD CONSTRAINT FK_5B635D35A310080 FOREIGN KEY (import_batch_id) REFERENCES import_batch (id)');
        $this->addSql('ALTER TABLE stop_row ADD CONSTRAINT FK_B7B5D05F34ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id)');
        $this->addSql('ALTER TABLE stop_row ADD CONSTRAINT FK_B7B5D05F5A310080 FOREIGN KEY (import_batch_id) REFERENCES import_batch (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_batch DROP FOREIGN KEY FK_155EFD1AA2B28FE8');
        $this->addSql('ALTER TABLE rejected_stop_row DROP FOREIGN KEY FK_5B635D35A310080');
        $this->addSql('ALTER TABLE stop_row DROP FOREIGN KEY FK_B7B5D05F34ECB4E6');
        $this->addSql('ALTER TABLE stop_row DROP FOREIGN KEY FK_B7B5D05F5A310080');
        $this->addSql('DROP TABLE import_batch');
        $this->addSql('DROP TABLE rejected_stop_row');
        $this->addSql('DROP TABLE stop_row');
    }
}

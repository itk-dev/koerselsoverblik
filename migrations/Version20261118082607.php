<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118082607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the schema of release 0.1.0.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE import_batch (id INT AUTO_INCREMENT NOT NULL, first_date DATE NOT NULL, last_date DATE NOT NULL, rows_deleted INT NOT NULL, rows_inserted INT NOT NULL, rows_rejected INT NOT NULL, warning_count INT NOT NULL, errors JSON NOT NULL, warnings JSON NOT NULL, mapping_name VARCHAR(64) NOT NULL, file_name VARCHAR(255) NOT NULL, uploaded_at DATETIME NOT NULL, uploaded_by_id INT DEFAULT NULL, INDEX IDX_155EFD1AA2B28FE8 (uploaded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE location_day_totals (id INT AUTO_INCREMENT NOT NULL, on_time INT NOT NULL, measured INT NOT NULL, service_date DATE NOT NULL, location_name VARCHAR(255) NOT NULL, tolerance_minutes INT NOT NULL, route_id INT NOT NULL, INDEX IDX_89E9761B64DD8396 (service_date), UNIQUE INDEX UNIQ_89E9761B34ECB4E664DD8396DE27CB46 (route_id, service_date, location_name), INDEX IDX_89E9761B34ECB4E6 (route_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE month_citizens (id INT AUTO_INCREMENT NOT NULL, first_date DATE NOT NULL, citizens INT NOT NULL, supplier_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_1F6B06835D6C884E2ADD6D8C (first_date, supplier_id), INDEX IDX_1F6B06832ADD6D8C (supplier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rejected_stop_row (id INT AUTO_INCREMENT NOT NULL, sheet_row_number INT NOT NULL, reason VARCHAR(32) NOT NULL, field VARCHAR(32) DEFAULT NULL, service_date DATE DEFAULT NULL, source_row_id INT NOT NULL, import_batch_id INT NOT NULL, UNIQUE INDEX UNIQ_5B635D3355D4C14 (source_row_id), INDEX IDX_5B635D364DD8396 (service_date), INDEX IDX_5B635D35A310080 (import_batch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE route (id INT AUTO_INCREMENT NOT NULL, source_key VARCHAR(255) NOT NULL, sub_agreement_id INT NOT NULL, UNIQUE INDEX UNIQ_2C42079DB64AEE8 (source_key), INDEX IDX_2C4207933DB4C1D (sub_agreement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE route_day_totals (id INT AUTO_INCREMENT NOT NULL, pickup_on_time INT NOT NULL, pickup_measured INT NOT NULL, drop_off_on_time INT NOT NULL, drop_off_measured INT NOT NULL, first_pickup_on_time INT NOT NULL, first_pickup_measured INT NOT NULL, last_drop_on_time INT NOT NULL, last_drop_measured INT NOT NULL, last_drop_up_to15 INT NOT NULL, last_drop_up_to30 INT NOT NULL, last_drop_over30 INT NOT NULL, trips INT NOT NULL, completed_trips INT NOT NULL, cancelled_trips INT NOT NULL, pending_trips INT NOT NULL, runs INT NOT NULL, solo_runs INT NOT NULL, service_date DATE NOT NULL, tolerance_minutes INT NOT NULL, route_id INT NOT NULL, INDEX IDX_3EB6BD2364DD8396 (service_date), UNIQUE INDEX UNIQ_3EB6BD2334ECB4E664DD8396 (route_id, service_date), INDEX IDX_3EB6BD2334ECB4E6 (route_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE route_run (id INT AUTO_INCREMENT NOT NULL, first_pickup_planned DATETIME DEFAULT NULL, first_pickup_actual DATETIME DEFAULT NULL, first_pickup_delay_seconds INT DEFAULT NULL, last_drop_planned DATETIME DEFAULT NULL, last_drop_actual DATETIME DEFAULT NULL, last_drop_delay_seconds INT DEFAULT NULL, cancelled TINYINT NOT NULL, peak_passengers INT DEFAULT NULL, service_date DATE NOT NULL, run INT NOT NULL, route_id INT NOT NULL, INDEX IDX_72FF67AA64DD8396 (service_date), UNIQUE INDEX UNIQ_72FF67AA34ECB4E664DD83965076A4C0 (route_id, service_date, run), INDEX IDX_72FF67AA34ECB4E6 (route_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE source_row (id INT AUTO_INCREMENT NOT NULL, source_values JSON NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stop_row (id INT AUTO_INCREMENT NOT NULL, row_key VARCHAR(255) DEFAULT NULL, service_date DATE NOT NULL, run INT NOT NULL, stop_type VARCHAR(16) NOT NULL, stop_status VARCHAR(16) NOT NULL, passengers_after INT DEFAULT NULL, next_distance_km DOUBLE PRECISION DEFAULT NULL, next_duration_min DOUBLE PRECISION DEFAULT NULL, planned_time DATETIME NOT NULL, actual_time DATETIME DEFAULT NULL, delay_seconds INT DEFAULT NULL, planned_solo TINYINT DEFAULT NULL, location_name VARCHAR(255) DEFAULT NULL, person_id VARCHAR(255) DEFAULT NULL, mapping_name VARCHAR(64) NOT NULL, source_row_id INT NOT NULL, route_id INT NOT NULL, import_batch_id INT NOT NULL, UNIQUE INDEX UNIQ_B7B5D05F355D4C14 (source_row_id), INDEX IDX_B7B5D05F34ECB4E664DD8396 (route_id, service_date), INDEX IDX_B7B5D05F64DD839666389536C33022F834ECB4E6217BBB47 (service_date, stop_type, stop_status, route_id, person_id), UNIQUE INDEX UNIQ_B7B5D05F9E60EEF6B274F263 (mapping_name, row_key), INDEX IDX_B7B5D05F34ECB4E6 (route_id), INDEX IDX_B7B5D05F5A310080 (import_batch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sub_agreement (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(255) NOT NULL, ignored TINYINT DEFAULT 0 NOT NULL, supplier_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_3C681C9AAEA34913 (reference), INDEX IDX_3C681C9A2ADD6D8C (supplier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE supplier (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_9B2A6C7E5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, last_login_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE import_batch ADD CONSTRAINT FK_155EFD1AA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE location_day_totals ADD CONSTRAINT FK_89E9761B34ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id)');
        $this->addSql('ALTER TABLE month_citizens ADD CONSTRAINT FK_1F6B06832ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rejected_stop_row ADD CONSTRAINT FK_5B635D3355D4C14 FOREIGN KEY (source_row_id) REFERENCES source_row (id)');
        $this->addSql('ALTER TABLE rejected_stop_row ADD CONSTRAINT FK_5B635D35A310080 FOREIGN KEY (import_batch_id) REFERENCES import_batch (id)');
        $this->addSql('ALTER TABLE route ADD CONSTRAINT FK_2C4207933DB4C1D FOREIGN KEY (sub_agreement_id) REFERENCES sub_agreement (id)');
        $this->addSql('ALTER TABLE route_day_totals ADD CONSTRAINT FK_3EB6BD2334ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id)');
        $this->addSql('ALTER TABLE route_run ADD CONSTRAINT FK_72FF67AA34ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id)');
        $this->addSql('ALTER TABLE stop_row ADD CONSTRAINT FK_B7B5D05F355D4C14 FOREIGN KEY (source_row_id) REFERENCES source_row (id)');
        $this->addSql('ALTER TABLE stop_row ADD CONSTRAINT FK_B7B5D05F34ECB4E6 FOREIGN KEY (route_id) REFERENCES route (id)');
        $this->addSql('ALTER TABLE stop_row ADD CONSTRAINT FK_B7B5D05F5A310080 FOREIGN KEY (import_batch_id) REFERENCES import_batch (id)');
        $this->addSql('ALTER TABLE sub_agreement ADD CONSTRAINT FK_3C681C9A2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_batch DROP FOREIGN KEY FK_155EFD1AA2B28FE8');
        $this->addSql('ALTER TABLE location_day_totals DROP FOREIGN KEY FK_89E9761B34ECB4E6');
        $this->addSql('ALTER TABLE month_citizens DROP FOREIGN KEY FK_1F6B06832ADD6D8C');
        $this->addSql('ALTER TABLE rejected_stop_row DROP FOREIGN KEY FK_5B635D3355D4C14');
        $this->addSql('ALTER TABLE rejected_stop_row DROP FOREIGN KEY FK_5B635D35A310080');
        $this->addSql('ALTER TABLE route DROP FOREIGN KEY FK_2C4207933DB4C1D');
        $this->addSql('ALTER TABLE route_day_totals DROP FOREIGN KEY FK_3EB6BD2334ECB4E6');
        $this->addSql('ALTER TABLE route_run DROP FOREIGN KEY FK_72FF67AA34ECB4E6');
        $this->addSql('ALTER TABLE stop_row DROP FOREIGN KEY FK_B7B5D05F355D4C14');
        $this->addSql('ALTER TABLE stop_row DROP FOREIGN KEY FK_B7B5D05F34ECB4E6');
        $this->addSql('ALTER TABLE stop_row DROP FOREIGN KEY FK_B7B5D05F5A310080');
        $this->addSql('ALTER TABLE sub_agreement DROP FOREIGN KEY FK_3C681C9A2ADD6D8C');
        $this->addSql('DROP TABLE import_batch');
        $this->addSql('DROP TABLE location_day_totals');
        $this->addSql('DROP TABLE month_citizens');
        $this->addSql('DROP TABLE rejected_stop_row');
        $this->addSql('DROP TABLE route');
        $this->addSql('DROP TABLE route_day_totals');
        $this->addSql('DROP TABLE route_run');
        $this->addSql('DROP TABLE source_row');
        $this->addSql('DROP TABLE stop_row');
        $this->addSql('DROP TABLE sub_agreement');
        $this->addSql('DROP TABLE supplier');
        $this->addSql('DROP TABLE `user`');
    }
}

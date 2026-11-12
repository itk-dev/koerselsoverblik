<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The new columns are empty for the stored rows. The application is not live, so no fill from the
 * copies is necessary. A new upload fills them.
 */
final class Version20261112190357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the remaining prototype fields to the stop rows';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stop_row ADD passengers INT DEFAULT NULL, ADD price_per_minute DOUBLE PRECISION DEFAULT NULL, ADD cancellation_notice_min DOUBLE PRECISION DEFAULT NULL, ADD cancellation_in_time TINYINT DEFAULT NULL, ADD location_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stop_row DROP passengers, DROP price_per_minute, DROP cancellation_notice_min, DROP cancellation_in_time, DROP location_name');
    }
}

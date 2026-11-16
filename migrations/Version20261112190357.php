<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The new column is empty for the stored rows. The application is not live, so no fill from the
 * copies is necessary. A new upload fills it.
 */
final class Version20261112190357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the location name to the stop rows';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stop_row ADD location_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stop_row DROP location_name');
    }
}

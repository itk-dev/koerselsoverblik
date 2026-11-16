<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115080815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the index on the date of the stop rows. The index of the citizens query starts with the date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_B7B5D05F64DD8396 ON stop_row');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_B7B5D05F64DD8396 ON stop_row (service_date)');
    }
}

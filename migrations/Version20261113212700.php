<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113212700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an index on the route and the date of each stop row, for the figure queries of a few routes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_B7B5D05F34ECB4E664DD8396 ON stop_row (route_id, service_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_B7B5D05F34ECB4E664DD8396 ON stop_row');
    }
}

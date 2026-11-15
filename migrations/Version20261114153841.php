<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114153841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an index on the stop rows that holds all columns of the query of the citizens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_B7B5D05F64DD839666389536C33022F834ECB4E6217BBB47 ON stop_row (service_date, stop_type, stop_status, route_id, person_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_B7B5D05F64DD839666389536C33022F834ECB4E6217BBB47 ON stop_row');
    }
}

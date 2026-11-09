<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109100042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the delay of each stop row, in seconds';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stop_row ADD delay_seconds INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stop_row DROP delay_seconds');
    }
}

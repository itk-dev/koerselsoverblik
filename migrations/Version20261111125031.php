<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111125031 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the flag that excludes a sub-agreement from the figures';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sub_agreement ADD ignored TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sub_agreement DROP ignored');
    }
}

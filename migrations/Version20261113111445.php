<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113111445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the peak passengers to each route run, for the real solo runs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_run ADD peak_passengers INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_run DROP peak_passengers');
    }
}

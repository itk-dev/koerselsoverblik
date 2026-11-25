<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125083714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the number of the row in the sheet of the file to the stop rows.';
    }

    /**
     * MariaDB commits before each DDL statement, so a transaction has no effect.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // The stored stop rows get no number. A read of their copies gives them their place on the date.
        $this->addSql('ALTER TABLE stop_row ADD sheet_row_number INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stop_row DROP sheet_row_number');
    }
}

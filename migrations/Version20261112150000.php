<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'katzen_waitlist.source: which site form produced the row';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE katzen_waitlist ADD source VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE katzen_waitlist DROP source');
    }
}

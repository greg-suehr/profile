<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250312212652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item ADD fooddb_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD scientific_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE item ADD subcategory VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE item ALTER description TYPE TEXT');
        $this->addSql('ALTER TABLE item ALTER description DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item DROP fooddb_id');
        $this->addSql('ALTER TABLE item DROP scientific_name');
        $this->addSql('ALTER TABLE item DROP subcategory');
        $this->addSql('ALTER TABLE item ALTER description TYPE VARCHAR(1000)');
        $this->addSql('ALTER TABLE item ALTER description SET NOT NULL');
    }
}

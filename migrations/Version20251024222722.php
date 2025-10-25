<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024222722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_receipt ADD location_id INT NOT NULL');
        $this->addSql('ALTER TABLE stock_receipt ADD CONSTRAINT FK_4A3FA6E664D218E FOREIGN KEY (location_id) REFERENCES stock_location (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_4A3FA6E664D218E ON stock_receipt (location_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_receipt DROP CONSTRAINT FK_4A3FA6E664D218E');
        $this->addSql('DROP INDEX IDX_4A3FA6E664D218E');
        $this->addSql('ALTER TABLE stock_receipt DROP location_id');
    }
}

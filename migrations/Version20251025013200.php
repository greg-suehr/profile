<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025013200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_receipt_item ADD stock_receipt_id INT NOT NULL');
        $this->addSql('ALTER TABLE stock_receipt_item ADD CONSTRAINT FK_A80B248185155F3A FOREIGN KEY (stock_receipt_id) REFERENCES stock_receipt (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A80B248185155F3A ON stock_receipt_item (stock_receipt_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_receipt_item DROP CONSTRAINT FK_A80B248185155F3A');
        $this->addSql('DROP INDEX IDX_A80B248185155F3A');
        $this->addSql('ALTER TABLE stock_receipt_item DROP stock_receipt_id');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025020800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_receipt_item DROP CONSTRAINT fk_a80b248196481bde');
        $this->addSql('DROP INDEX uniq_a80b248196481bde');
        $this->addSql('ALTER TABLE stock_receipt_item RENAME COLUMN stock_transactions_id TO stock_transaction_id');
        $this->addSql('ALTER TABLE stock_receipt_item ADD CONSTRAINT FK_A80B248188237244 FOREIGN KEY (stock_transaction_id) REFERENCES stock_transaction (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A80B248188237244 ON stock_receipt_item (stock_transaction_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_receipt_item DROP CONSTRAINT FK_A80B248188237244');
        $this->addSql('DROP INDEX UNIQ_A80B248188237244');
        $this->addSql('ALTER TABLE stock_receipt_item RENAME COLUMN stock_transaction_id TO stock_transactions_id');
        $this->addSql('ALTER TABLE stock_receipt_item ADD CONSTRAINT fk_a80b248196481bde FOREIGN KEY (stock_transactions_id) REFERENCES stock_transaction (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_a80b248196481bde ON stock_receipt_item (stock_transactions_id)');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024222636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE purchase ADD location_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_6117D13B64D218E FOREIGN KEY (location_id) REFERENCES stock_location (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6117D13B64D218E ON purchase (location_id)');
        $this->addSql('ALTER TABLE stock_receipt ADD purchase_id INT NOT NULL');
        $this->addSql('ALTER TABLE stock_receipt ADD CONSTRAINT FK_4A3FA6E6558FBEB9 FOREIGN KEY (purchase_id) REFERENCES purchase (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_4A3FA6E6558FBEB9 ON stock_receipt (purchase_id)');
        $this->addSql('ALTER TABLE stock_receipt_item ADD stock_target_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_receipt_item ADD CONSTRAINT FK_A80B24818D13ED8E FOREIGN KEY (stock_target_id) REFERENCES stock_target (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A80B24818D13ED8E ON stock_receipt_item (stock_target_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE purchase DROP CONSTRAINT FK_6117D13B64D218E');
        $this->addSql('DROP INDEX IDX_6117D13B64D218E');
        $this->addSql('ALTER TABLE purchase DROP location_id');
        $this->addSql('ALTER TABLE stock_receipt DROP CONSTRAINT FK_4A3FA6E6558FBEB9');
        $this->addSql('DROP INDEX IDX_4A3FA6E6558FBEB9');
        $this->addSql('ALTER TABLE stock_receipt DROP purchase_id');
        $this->addSql('ALTER TABLE stock_receipt_item DROP CONSTRAINT FK_A80B24818D13ED8E');
        $this->addSql('DROP INDEX IDX_A80B24818D13ED8E');
        $this->addSql('ALTER TABLE stock_receipt_item DROP stock_target_id');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251104044956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE vendor ADD address_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD postal_code VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD phone_digits VARCHAR(15) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD vendor_aliases JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD vendor_domains JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE vendor DROP address_hash');
        $this->addSql('ALTER TABLE vendor DROP postal_code');
        $this->addSql('ALTER TABLE vendor DROP phone_digits');
        $this->addSql('ALTER TABLE vendor DROP vendor_aliases');
        $this->addSql('ALTER TABLE vendor DROP vendor_domains');
    }
}

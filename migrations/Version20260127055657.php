<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127055657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE import_batch ADD metadata JSON DEFAULT NULL');
        $this->addSql('ALTER INDEX idx_mapping_learning_entity_type RENAME TO idx_import_mapping_learning_entity_type');
        $this->addSql('ALTER INDEX idx_mapping_learning_fingerprint RENAME TO idx_import_mapping_learning_fingerprint');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER INDEX idx_import_mapping_learning_fingerprint RENAME TO idx_mapping_learning_fingerprint');
        $this->addSql('ALTER INDEX idx_import_mapping_learning_entity_type RENAME TO idx_mapping_learning_entity_type');
        $this->addSql('ALTER TABLE import_batch DROP metadata');
    }
}

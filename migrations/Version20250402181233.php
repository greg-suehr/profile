<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250402181233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recipe_ingredient DROP CONSTRAINT fk_22d1fe13f476e05c');
        $this->addSql('DROP INDEX idx_22d1fe13f476e05c');
        $this->addSql('ALTER TABLE recipe_ingredient RENAME COLUMN unit_id_id TO unit_id');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT FK_22D1FE13F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_22D1FE13F8BD700D ON recipe_ingredient (unit_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recipe_ingredient DROP CONSTRAINT FK_22D1FE13F8BD700D');
        $this->addSql('DROP INDEX IDX_22D1FE13F8BD700D');
        $this->addSql('ALTER TABLE recipe_ingredient RENAME COLUMN unit_id TO unit_id_id');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT fk_22d1fe13f476e05c FOREIGN KEY (unit_id_id) REFERENCES unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_22d1fe13f476e05c ON recipe_ingredient (unit_id_id)');
    }
}

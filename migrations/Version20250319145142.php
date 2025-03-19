<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250319145142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recipe ADD serving_unit_id INT NOT NULL');
        $this->addSql('ALTER TABLE recipe DROP recipe_id');
        $this->addSql('ALTER TABLE recipe DROP serving_unit');
        $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B137F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B137EC3181A7 FOREIGN KEY (serving_unit_id) REFERENCES unit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_DA88B137F675F31B ON recipe (author_id)');
        $this->addSql('CREATE INDEX IDX_DA88B137EC3181A7 ON recipe (serving_unit_id)');
        $this->addSql('ALTER TABLE recipe_ingredient DROP CONSTRAINT fk_22d1fe1369574a48');
        $this->addSql('DROP INDEX idx_22d1fe1369574a48');
        $this->addSql('ALTER TABLE recipe_ingredient RENAME COLUMN recipe_id_id TO recipe_id');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT FK_22D1FE1359D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_22D1FE1359D8A214 ON recipe_ingredient (recipe_id)');
        $this->addSql('ALTER TABLE recipe_instruction DROP CONSTRAINT fk_af48af3269574a48');
        $this->addSql('DROP INDEX idx_af48af3269574a48');
        $this->addSql('ALTER TABLE recipe_instruction RENAME COLUMN recipe_id_id TO recipe_id');
        $this->addSql('ALTER TABLE recipe_instruction ADD CONSTRAINT FK_AF48AF3259D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_AF48AF3259D8A214 ON recipe_instruction (recipe_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recipe_ingredient DROP CONSTRAINT FK_22D1FE1359D8A214');
        $this->addSql('DROP INDEX IDX_22D1FE1359D8A214');
        $this->addSql('ALTER TABLE recipe_ingredient RENAME COLUMN recipe_id TO recipe_id_id');
        $this->addSql('ALTER TABLE recipe_ingredient ADD CONSTRAINT fk_22d1fe1369574a48 FOREIGN KEY (recipe_id_id) REFERENCES recipe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_22d1fe1369574a48 ON recipe_ingredient (recipe_id_id)');
        $this->addSql('ALTER TABLE recipe_instruction DROP CONSTRAINT FK_AF48AF3259D8A214');
        $this->addSql('DROP INDEX IDX_AF48AF3259D8A214');
        $this->addSql('ALTER TABLE recipe_instruction RENAME COLUMN recipe_id TO recipe_id_id');
        $this->addSql('ALTER TABLE recipe_instruction ADD CONSTRAINT fk_af48af3269574a48 FOREIGN KEY (recipe_id_id) REFERENCES recipe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_af48af3269574a48 ON recipe_instruction (recipe_id_id)');
        $this->addSql('ALTER TABLE recipe DROP CONSTRAINT FK_DA88B137F675F31B');
        $this->addSql('ALTER TABLE recipe DROP CONSTRAINT FK_DA88B137EC3181A7');
        $this->addSql('DROP INDEX IDX_DA88B137F675F31B');
        $this->addSql('DROP INDEX IDX_DA88B137EC3181A7');
        $this->addSql('ALTER TABLE recipe ADD serving_unit INT NOT NULL');
        $this->addSql('ALTER TABLE recipe RENAME COLUMN serving_unit_id TO recipe_id');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250328165219 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog_post DROP CONSTRAINT fk_ba5ae01d3569d950');
        $this->addSql('DROP INDEX uniq_ba5ae01d3569d950');
        $this->addSql('ALTER TABLE blog_post ADD featured_image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE blog_post DROP featured_image_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog_post ADD featured_image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE blog_post DROP featured_image');
        $this->addSql('ALTER TABLE blog_post ADD CONSTRAINT fk_ba5ae01d3569d950 FOREIGN KEY (featured_image_id) REFERENCES post_image (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_ba5ae01d3569d950 ON blog_post (featured_image_id)');
    }
}

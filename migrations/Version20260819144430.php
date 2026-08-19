<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819144430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status column to blog_post for the blog-status kanban board';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_post ADD status VARCHAR(20) NOT NULL DEFAULT \'submitted\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_post DROP status');
    }
}

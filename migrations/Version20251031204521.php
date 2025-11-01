<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251031204521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "order" ADD billing_status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD voided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD void_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD tax_rate NUMERIC(10, 6) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD discount_amount NUMERIC(15, 4) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD invoiced_amount NUMERIC(15, 4) NOT NULL');
        $this->addSql('ALTER TABLE "order" ADD paid_amount NUMERIC(15, 4) NOT NULL');
        $this->addSql('ALTER TABLE "order" ALTER status TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE "order" ALTER status SET NOT NULL');
        $this->addSql('ALTER TABLE "order" ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE "order" ALTER created_at SET NOT NULL');
        $this->addSql('ALTER TABLE "order" ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE "order" ALTER updated_at SET NOT NULL');
        $this->addSql('ALTER TABLE "order" ALTER subtotal TYPE NUMERIC(15, 4)');
        $this->addSql('ALTER TABLE "order" ALTER tax_amount TYPE NUMERIC(15, 4)');
        $this->addSql('ALTER TABLE "order" ALTER total_amount TYPE NUMERIC(15, 4)');
        $this->addSql('ALTER TABLE order_item ALTER quantity TYPE NUMERIC(18, 6)');
        $this->addSql('ALTER TABLE order_item ALTER quantity SET NOT NULL');
        $this->addSql('ALTER TABLE order_item ALTER unit_price TYPE NUMERIC(18, 6)');
        $this->addSql('ALTER TABLE order_item ALTER unit_price SET NOT NULL');
        $this->addSql('ALTER TABLE order_item ALTER cogs TYPE NUMERIC(18, 6)');
        $this->addSql('ALTER TABLE price_history ALTER unit_price TYPE NUMERIC(18, 6)');
        $this->addSql('ALTER TABLE price_history ALTER quantity_purchased TYPE NUMERIC(18, 6)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_item ALTER quantity TYPE INT');
        $this->addSql('ALTER TABLE order_item ALTER quantity DROP NOT NULL');
        $this->addSql('ALTER TABLE order_item ALTER unit_price TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE order_item ALTER unit_price DROP NOT NULL');
        $this->addSql('ALTER TABLE order_item ALTER cogs TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE price_history ALTER unit_price TYPE NUMERIC(10, 4)');
        $this->addSql('ALTER TABLE price_history ALTER quantity_purchased TYPE NUMERIC(10, 3)');
        $this->addSql('ALTER TABLE "order" DROP billing_status');
        $this->addSql('ALTER TABLE "order" DROP voided_at');
        $this->addSql('ALTER TABLE "order" DROP void_reason');
        $this->addSql('ALTER TABLE "order" DROP tax_rate');
        $this->addSql('ALTER TABLE "order" DROP discount_amount');
        $this->addSql('ALTER TABLE "order" DROP invoiced_amount');
        $this->addSql('ALTER TABLE "order" DROP paid_amount');
        $this->addSql('ALTER TABLE "order" ALTER status TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE "order" ALTER status DROP NOT NULL');
        $this->addSql('ALTER TABLE "order" ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE "order" ALTER created_at DROP NOT NULL');
        $this->addSql('ALTER TABLE "order" ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE "order" ALTER updated_at DROP NOT NULL');
        $this->addSql('ALTER TABLE "order" ALTER subtotal TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE "order" ALTER tax_amount TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE "order" ALTER total_amount TYPE NUMERIC(10, 2)');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251023005202 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE purchase_stock_receipt (purchase_id INT NOT NULL, stock_receipt_id INT NOT NULL, PRIMARY KEY(purchase_id, stock_receipt_id))');
        $this->addSql('CREATE INDEX IDX_9C2D1FF9558FBEB9 ON purchase_stock_receipt (purchase_id)');
        $this->addSql('CREATE INDEX IDX_9C2D1FF985155F3A ON purchase_stock_receipt (stock_receipt_id)');
        $this->addSql('CREATE TABLE stock_receipt_item_purchase_item (stock_receipt_item_id INT NOT NULL, purchase_item_id INT NOT NULL, PRIMARY KEY(stock_receipt_item_id, purchase_item_id))');
        $this->addSql('CREATE INDEX IDX_976F7DA7E08C8C01 ON stock_receipt_item_purchase_item (stock_receipt_item_id)');
        $this->addSql('CREATE INDEX IDX_976F7DA79B59827 ON stock_receipt_item_purchase_item (purchase_item_id)');
        $this->addSql('ALTER TABLE purchase_stock_receipt ADD CONSTRAINT FK_9C2D1FF9558FBEB9 FOREIGN KEY (purchase_id) REFERENCES purchase (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE purchase_stock_receipt ADD CONSTRAINT FK_9C2D1FF985155F3A FOREIGN KEY (stock_receipt_id) REFERENCES stock_receipt (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stock_receipt_item_purchase_item ADD CONSTRAINT FK_976F7DA7E08C8C01 FOREIGN KEY (stock_receipt_item_id) REFERENCES stock_receipt_item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stock_receipt_item_purchase_item ADD CONSTRAINT FK_976F7DA79B59827 FOREIGN KEY (purchase_item_id) REFERENCES purchase_item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE customer ALTER payment_terms TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE customer ALTER payment_terms DROP DEFAULT');
        $this->addSql('ALTER TABLE customer ALTER ar_balance TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE customer ALTER ar_balance DROP DEFAULT');
        $this->addSql('ALTER TABLE "order" ALTER subtotal TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE "order" ALTER subtotal DROP DEFAULT');
        $this->addSql('ALTER TABLE "order" ALTER tax_amount TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE "order" ALTER tax_amount DROP DEFAULT');
        $this->addSql('ALTER TABLE "order" ALTER total_amount TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE "order" ALTER total_amount DROP DEFAULT');
        $this->addSql('ALTER TABLE "order" ALTER fulfillment_status TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE "order" ALTER fulfillment_status DROP DEFAULT');
        $this->addSql('ALTER TABLE order_item ALTER cogs TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE order_item ALTER cogs DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase ADD vendor_id INT NOT NULL');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_6117D13BF603EE73 FOREIGN KEY (vendor_id) REFERENCES vendor (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6117D13BF603EE73 ON purchase (vendor_id)');
        $this->addSql('ALTER TABLE stock_receipt ADD receipt_number VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE stock_receipt ADD received_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE stock_receipt ADD status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE stock_receipt ADD notes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_receipt ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE stock_receipt ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE stock_receipt ADD received_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE stock_receipt ADD CONSTRAINT FK_4A3FA6E66F8DDD17 FOREIGN KEY (received_by_id) REFERENCES katzen_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_4A3FA6E66F8DDD17 ON stock_receipt (received_by_id)');
        $this->addSql('ALTER TABLE stock_receipt_item ADD qty_received NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE stock_receipt_item ADD qty_returned NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE stock_receipt_item ADD lot_number VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_receipt_item ADD expiration_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_receipt_item ADD notes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_receipt_item ADD stock_transactions_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_receipt_item ADD CONSTRAINT FK_A80B248196481BDE FOREIGN KEY (stock_transactions_id) REFERENCES stock_transaction (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A80B248196481BDE ON stock_receipt_item (stock_transactions_id)');
        $this->addSql('ALTER TABLE stock_transaction ALTER effective_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE stock_transaction ALTER effective_date DROP DEFAULT');
        $this->addSql('ALTER TABLE stock_transaction ALTER recorded_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE stock_transaction ALTER recorded_at DROP DEFAULT');
        $this->addSql('ALTER TABLE stock_transaction ALTER status TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE stock_transaction ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE vendor ADD name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE vendor ADD vendor_code VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE vendor ADD email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD phone VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD fax VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD website VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD billing_address TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD shipping_address TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD tax_id VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD tax_classification VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD payment_terms VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD credit_limit NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD current_balance NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE vendor ADD notes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE vendor ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE vendor ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE purchase_stock_receipt DROP CONSTRAINT FK_9C2D1FF9558FBEB9');
        $this->addSql('ALTER TABLE purchase_stock_receipt DROP CONSTRAINT FK_9C2D1FF985155F3A');
        $this->addSql('ALTER TABLE stock_receipt_item_purchase_item DROP CONSTRAINT FK_976F7DA7E08C8C01');
        $this->addSql('ALTER TABLE stock_receipt_item_purchase_item DROP CONSTRAINT FK_976F7DA79B59827');
        $this->addSql('DROP TABLE purchase_stock_receipt');
        $this->addSql('DROP TABLE stock_receipt_item_purchase_item');
        $this->addSql('ALTER TABLE purchase DROP CONSTRAINT FK_6117D13BF603EE73');
        $this->addSql('DROP INDEX IDX_6117D13BF603EE73');
        $this->addSql('ALTER TABLE purchase DROP vendor_id');
        $this->addSql('ALTER TABLE vendor DROP name');
        $this->addSql('ALTER TABLE vendor DROP vendor_code');
        $this->addSql('ALTER TABLE vendor DROP email');
        $this->addSql('ALTER TABLE vendor DROP phone');
        $this->addSql('ALTER TABLE vendor DROP fax');
        $this->addSql('ALTER TABLE vendor DROP website');
        $this->addSql('ALTER TABLE vendor DROP billing_address');
        $this->addSql('ALTER TABLE vendor DROP shipping_address');
        $this->addSql('ALTER TABLE vendor DROP tax_id');
        $this->addSql('ALTER TABLE vendor DROP tax_classification');
        $this->addSql('ALTER TABLE vendor DROP payment_terms');
        $this->addSql('ALTER TABLE vendor DROP credit_limit');
        $this->addSql('ALTER TABLE vendor DROP current_balance');
        $this->addSql('ALTER TABLE vendor DROP status');
        $this->addSql('ALTER TABLE vendor DROP notes');
        $this->addSql('ALTER TABLE vendor DROP created_at');
        $this->addSql('ALTER TABLE vendor DROP updated_at');
        $this->addSql('ALTER TABLE stock_transaction ALTER effective_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE stock_transaction ALTER effective_date SET DEFAULT \'now()\'');
        $this->addSql('ALTER TABLE stock_transaction ALTER recorded_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE stock_transaction ALTER recorded_at SET DEFAULT \'now()\'');
        $this->addSql('ALTER TABLE stock_transaction ALTER status TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE stock_transaction ALTER status SET DEFAULT \'peending\'');
        $this->addSql('ALTER TABLE stock_receipt DROP CONSTRAINT FK_4A3FA6E66F8DDD17');
        $this->addSql('DROP INDEX IDX_4A3FA6E66F8DDD17');
        $this->addSql('ALTER TABLE stock_receipt DROP receipt_number');
        $this->addSql('ALTER TABLE stock_receipt DROP received_date');
        $this->addSql('ALTER TABLE stock_receipt DROP status');
        $this->addSql('ALTER TABLE stock_receipt DROP notes');
        $this->addSql('ALTER TABLE stock_receipt DROP created_at');
        $this->addSql('ALTER TABLE stock_receipt DROP updated_at');
        $this->addSql('ALTER TABLE stock_receipt DROP received_by_id');
        $this->addSql('ALTER TABLE order_item ALTER cogs TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE order_item ALTER cogs SET DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE stock_receipt_item DROP CONSTRAINT FK_A80B248196481BDE');
        $this->addSql('DROP INDEX UNIQ_A80B248196481BDE');
        $this->addSql('ALTER TABLE stock_receipt_item DROP qty_received');
        $this->addSql('ALTER TABLE stock_receipt_item DROP qty_returned');
        $this->addSql('ALTER TABLE stock_receipt_item DROP lot_number');
        $this->addSql('ALTER TABLE stock_receipt_item DROP expiration_date');
        $this->addSql('ALTER TABLE stock_receipt_item DROP notes');
        $this->addSql('ALTER TABLE stock_receipt_item DROP stock_transactions_id');
        $this->addSql('ALTER TABLE customer ALTER payment_terms TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE customer ALTER payment_terms SET DEFAULT \'due_on_recipt\'');
        $this->addSql('ALTER TABLE customer ALTER ar_balance TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE customer ALTER ar_balance SET DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE "order" ALTER subtotal TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE "order" ALTER subtotal SET DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE "order" ALTER tax_amount TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE "order" ALTER tax_amount SET DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE "order" ALTER total_amount TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE "order" ALTER total_amount SET DEFAULT \'0.00\'');
        $this->addSql('ALTER TABLE "order" ALTER fulfillment_status TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE "order" ALTER fulfillment_status SET DEFAULT \'unfulfilled\'');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240919065948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE move DROP CONSTRAINT fk_ef3e37786dc541a8');
        $this->addSql('DROP INDEX idx_ef3e37786dc541a8');
        $this->addSql('ALTER TABLE move RENAME COLUMN move_id TO notation_id');
        $this->addSql('ALTER TABLE move ADD CONSTRAINT FK_EF3E37789680B7F7 FOREIGN KEY (notation_id) REFERENCES notation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EF3E37789680B7F7 ON move (notation_id)');
        $this->addSql('ALTER TABLE notation RENAME COLUMN notation TO text');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_268BC95973CFFB73B8BA7C7 ON notation (FEN, text)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE move DROP CONSTRAINT FK_EF3E37789680B7F7');
        $this->addSql('DROP INDEX IDX_EF3E37789680B7F7');
        $this->addSql('ALTER TABLE move RENAME COLUMN notation_id TO move_id');
        $this->addSql('ALTER TABLE move ADD CONSTRAINT fk_ef3e37786dc541a8 FOREIGN KEY (move_id) REFERENCES notation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_ef3e37786dc541a8 ON move (move_id)');
        $this->addSql('DROP INDEX UNIQ_268BC95973CFFB73B8BA7C7');
        $this->addSql('ALTER TABLE notation RENAME COLUMN text TO notation');
    }
}

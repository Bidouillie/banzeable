<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241227102400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE chapter_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE chapter (id INT NOT NULL, name VARCHAR(255) NOT NULL, pgn TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE variation ADD chapter_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE variation ADD CONSTRAINT FK_629B33EA579F4768 FOREIGN KEY (chapter_id) REFERENCES chapter (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_629B33EA579F4768 ON variation (chapter_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE variation DROP CONSTRAINT FK_629B33EA579F4768');
        $this->addSql('DROP SEQUENCE chapter_id_seq CASCADE');
        $this->addSql('DROP TABLE chapter');
        $this->addSql('DROP INDEX IDX_629B33EA579F4768');
        $this->addSql('ALTER TABLE variation DROP chapter_id');
    }
}

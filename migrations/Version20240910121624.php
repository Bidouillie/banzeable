<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240910121624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE move_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE notation_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE move (id INT NOT NULL, variation_id INT NOT NULL, move_id INT NOT NULL, position SMALLINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EF3E37785182BFD8 ON move (variation_id)');
        $this->addSql('CREATE INDEX IDX_EF3E37786DC541A8 ON move (move_id)');
        $this->addSql('CREATE TABLE move_notation (move_id INT NOT NULL, notation_id INT NOT NULL, PRIMARY KEY(move_id, notation_id))');
        $this->addSql('CREATE INDEX IDX_F795B5046DC541A8 ON move_notation (move_id)');
        $this->addSql('CREATE INDEX IDX_F795B5049680B7F7 ON move_notation (notation_id)');
        $this->addSql('CREATE TABLE notation (id INT NOT NULL, fen VARCHAR(255) NOT NULL, notation VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE move ADD CONSTRAINT FK_EF3E37785182BFD8 FOREIGN KEY (variation_id) REFERENCES variation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE move ADD CONSTRAINT FK_EF3E37786DC541A8 FOREIGN KEY (move_id) REFERENCES notation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE move_notation ADD CONSTRAINT FK_F795B5046DC541A8 FOREIGN KEY (move_id) REFERENCES move (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE move_notation ADD CONSTRAINT FK_F795B5049680B7F7 FOREIGN KEY (notation_id) REFERENCES notation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE move_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE notation_id_seq CASCADE');
        $this->addSql('ALTER TABLE move DROP CONSTRAINT FK_EF3E37785182BFD8');
        $this->addSql('ALTER TABLE move DROP CONSTRAINT FK_EF3E37786DC541A8');
        $this->addSql('ALTER TABLE move_notation DROP CONSTRAINT FK_F795B5046DC541A8');
        $this->addSql('ALTER TABLE move_notation DROP CONSTRAINT FK_F795B5049680B7F7');
        $this->addSql('DROP TABLE move');
        $this->addSql('DROP TABLE move_notation');
        $this->addSql('DROP TABLE notation');
    }
}

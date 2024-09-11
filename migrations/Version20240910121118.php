<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240910121118 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE pgn_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE variation_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE pgn (id INT NOT NULL, event VARCHAR(255) NOT NULL, site VARCHAR(255) NOT NULL, date DATE NOT NULL, round VARCHAR(255) NOT NULL, white VARCHAR(255) NOT NULL, black VARCHAR(255) NOT NULL, result VARCHAR(255) NOT NULL, fen VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE variation (id INT NOT NULL, course_id INT NOT NULL, pgn_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, fen VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_629B33EA591CC992 ON variation (course_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_629B33EAD30B9B2D ON variation (pgn_id)');
        $this->addSql('ALTER TABLE variation ADD CONSTRAINT FK_629B33EA591CC992 FOREIGN KEY (course_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE variation ADD CONSTRAINT FK_629B33EAD30B9B2D FOREIGN KEY (pgn_id) REFERENCES pgn (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE pgn_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE variation_id_seq CASCADE');
        $this->addSql('ALTER TABLE variation DROP CONSTRAINT FK_629B33EA591CC992');
        $this->addSql('ALTER TABLE variation DROP CONSTRAINT FK_629B33EAD30B9B2D');
        $this->addSql('DROP TABLE pgn');
        $this->addSql('DROP TABLE variation');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250127061507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE move (fen_from VARCHAR(255) NOT NULL, fen_to VARCHAR(255) NOT NULL, course_id INT NOT NULL, san VARCHAR(255) NOT NULL, PRIMARY KEY(fen_from, fen_to, course_id))');
        $this->addSql('CREATE INDEX IDX_EF3E3778591CC992 ON move (course_id)');
        $this->addSql('CREATE INDEX IDX_EF3E3778591CC992BACB5D4B ON move (course_id, fen_from)');
        $this->addSql('CREATE INDEX IDX_EF3E3778591CC99214FA9C5F ON move (course_id, fen_to)');
        $this->addSql('CREATE TABLE position (fen VARCHAR(255) NOT NULL, course_id INT NOT NULL, PRIMARY KEY(fen, course_id))');
        $this->addSql('CREATE INDEX IDX_462CE4F5591CC992 ON position (course_id)');
        $this->addSql('ALTER TABLE move ADD CONSTRAINT FK_EF3E3778591CC992 FOREIGN KEY (course_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE move ADD CONSTRAINT FK_EF3E3778591CC992BACB5D4B FOREIGN KEY (course_id, fen_from) REFERENCES position (course_id, fen) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE move ADD CONSTRAINT FK_EF3E3778591CC99214FA9C5F FOREIGN KEY (course_id, fen_to) REFERENCES position (course_id, fen) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE position ADD CONSTRAINT FK_462CE4F5591CC992 FOREIGN KEY (course_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE move DROP CONSTRAINT FK_EF3E3778591CC992');
        $this->addSql('ALTER TABLE move DROP CONSTRAINT FK_EF3E3778591CC992BACB5D4B');
        $this->addSql('ALTER TABLE move DROP CONSTRAINT FK_EF3E3778591CC99214FA9C5F');
        $this->addSql('ALTER TABLE position DROP CONSTRAINT FK_462CE4F5591CC992');
        $this->addSql('DROP TABLE move');
        $this->addSql('DROP TABLE position');
    }
}

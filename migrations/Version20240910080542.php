<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240910080542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE course_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE course (id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE user_course (user_id INT NOT NULL, course_id INT NOT NULL, PRIMARY KEY(user_id, course_id))');
        $this->addSql('CREATE INDEX IDX_73CC7484A76ED395 ON user_course (user_id)');
        $this->addSql('CREATE INDEX IDX_73CC7484591CC992 ON user_course (course_id)');
        $this->addSql('ALTER TABLE user_course ADD CONSTRAINT FK_73CC7484A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_course ADD CONSTRAINT FK_73CC7484591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE course_id_seq CASCADE');
        $this->addSql('ALTER TABLE user_course DROP CONSTRAINT FK_73CC7484A76ED395');
        $this->addSql('ALTER TABLE user_course DROP CONSTRAINT FK_73CC7484591CC992');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE user_course');
    }
}

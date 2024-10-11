<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241010060034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE studies (user_id INT NOT NULL, course_id INT NOT NULL, PRIMARY KEY(user_id, course_id))');
        $this->addSql('CREATE INDEX IDX_C3A91A3FA76ED395 ON studies (user_id)');
        $this->addSql('CREATE INDEX IDX_C3A91A3F591CC992 ON studies (course_id)');
        $this->addSql('ALTER TABLE studies ADD CONSTRAINT FK_C3A91A3FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE studies ADD CONSTRAINT FK_C3A91A3F591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE studies DROP CONSTRAINT FK_C3A91A3FA76ED395');
        $this->addSql('ALTER TABLE studies DROP CONSTRAINT FK_C3A91A3F591CC992');
        $this->addSql('DROP TABLE studies');
    }
}

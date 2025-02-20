<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250220064847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE move_popularity_masters (since VARCHAR(255) NOT NULL, until VARCHAR(255) NOT NULL, fen VARCHAR(255) NOT NULL, lan VARCHAR(255) NOT NULL, date_created DATE NOT NULL, white INT DEFAULT NULL, black INT DEFAULT NULL, draws INT DEFAULT NULL, opening VARCHAR(255) DEFAULT NULL, PRIMARY KEY(since, until, fen, lan))');
        $this->addSql('INSERT INTO move_popularity_masters SELECT * FROM move_popularity_master');
        $this->addSql('DROP TABLE move_popularity_master');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE move_popularity_master (since VARCHAR(255) NOT NULL, until VARCHAR(255) NOT NULL, fen VARCHAR(255) NOT NULL, lan VARCHAR(255) NOT NULL, date_created DATE NOT NULL, white INT DEFAULT NULL, black INT DEFAULT NULL, draws INT DEFAULT NULL, opening VARCHAR(255) DEFAULT NULL, PRIMARY KEY(since, until, fen, lan))');
        $this->addSql('INSERT INTO move_popularity_master SELECT * FROM move_popularity_masters');
        $this->addSql('DROP TABLE move_popularity_masters');
    }
}

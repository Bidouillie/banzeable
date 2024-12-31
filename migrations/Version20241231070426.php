<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241231070426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE move_popularity (variant VARCHAR(255) NOT NULL, speeds VARCHAR(255) NOT NULL, ratings VARCHAR(255) NOT NULL, since VARCHAR(255) NOT NULL, until VARCHAR(255) NOT NULL, fen VARCHAR(255) NOT NULL, san VARCHAR(255) NOT NULL, date_created DATE NOT NULL, white INT NOT NULL, black INT NOT NULL, draws INT NOT NULL, opening VARCHAR(255) DEFAULT NULL, PRIMARY KEY(variant, speeds, ratings, since, until, fen, san))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE move_popularity');
    }
}

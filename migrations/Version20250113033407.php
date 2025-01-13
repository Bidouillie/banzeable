<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250113033407 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE move_popularity DROP CONSTRAINT move_popularity_pkey');
        $this->addSql('ALTER TABLE move_popularity DROP variant');
        $this->addSql('ALTER TABLE move_popularity ADD PRIMARY KEY (speeds, ratings, since, until, fen, san)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX move_popularity_pkey');
        $this->addSql('ALTER TABLE move_popularity ADD variant VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE move_popularity ADD PRIMARY KEY (variant, speeds, ratings, since, until, fen, san)');
    }
}

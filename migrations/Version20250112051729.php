<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250112051729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE move_popularity DROP next_moves_loaded');
        $this->addSql('ALTER TABLE move_popularity ALTER white DROP NOT NULL');
        $this->addSql('ALTER TABLE move_popularity ALTER black DROP NOT NULL');
        $this->addSql('ALTER TABLE move_popularity ALTER draws DROP NOT NULL');
        $this->addSql('ALTER TABLE move_popularity_master DROP next_moves_loaded');
        $this->addSql('ALTER TABLE move_popularity_master ALTER white DROP NOT NULL');
        $this->addSql('ALTER TABLE move_popularity_master ALTER black DROP NOT NULL');
        $this->addSql('ALTER TABLE move_popularity_master ALTER draws DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE move_popularity ADD next_moves_loaded BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE move_popularity ALTER white SET NOT NULL');
        $this->addSql('ALTER TABLE move_popularity ALTER black SET NOT NULL');
        $this->addSql('ALTER TABLE move_popularity ALTER draws SET NOT NULL');
        $this->addSql('ALTER TABLE move_popularity_master ADD next_moves_loaded BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE move_popularity_master ALTER white SET NOT NULL');
        $this->addSql('ALTER TABLE move_popularity_master ALTER black SET NOT NULL');
        $this->addSql('ALTER TABLE move_popularity_master ALTER draws SET NOT NULL');
    }
}

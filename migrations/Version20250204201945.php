<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250204201945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE move_popularity RENAME COLUMN san TO lan');
        $this->addSql('ALTER TABLE move_popularity_master RENAME COLUMN san TO lan');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE move_popularity_master RENAME COLUMN lan TO san');
        $this->addSql('ALTER TABLE move_popularity RENAME COLUMN lan TO san');
    }
}

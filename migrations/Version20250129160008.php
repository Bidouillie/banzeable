<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250129160008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE move ADD selected_percentage NUMERIC(10, 9) NOT NULL');
        $this->addSql('ALTER TABLE move RENAME COLUMN san TO lan');
        $this->addSql('ALTER TABLE position ADD expected_percentage NUMERIC(10, 9) DEFAULT \'1\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE move DROP selected_percentage');
        $this->addSql('ALTER TABLE move RENAME COLUMN lan TO san');
        $this->addSql('ALTER TABLE position DROP expected_percentage');
    }
}

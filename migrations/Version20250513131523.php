<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250513131523 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE repertoire_position ADD gply SMALLINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE move ALTER selected_percentage TYPE NUMERIC(10, 5)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE repertoire_position DROP gply');
        $this->addSql('ALTER TABLE move ALTER selected_percentage TYPE NUMERIC(10, 9)');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250331071003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE position RENAME TO repertoire_position');
        $this->addSql('ALTER INDEX idx_462ce4f5591cc992 RENAME TO IDX_2CEBC651591CC992');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE repertoire_position RENAME TO position');
        $this->addSql('ALTER INDEX idx_2cebc651591cc992 RENAME TO idx_462ce4f5591cc992');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250331110402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE position (fen VARCHAR(255) NOT NULL, evaluation NUMERIC(10, 2) DEFAULT NULL, mate SMALLINT DEFAULT 0, PRIMARY KEY(fen))');
        $this->addSql('INSERT INTO position(fen) SELECT fen FROM repertoire_position GROUP BY fen');
        $this->addSql('ALTER TABLE repertoire_position ADD CONSTRAINT FK_2CEBC65119BBD3D FOREIGN KEY (fen) REFERENCES position (fen) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_2CEBC65119BBD3D ON repertoire_position (fen)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE repertoire_position DROP CONSTRAINT FK_2CEBC65119BBD3D');
        $this->addSql('DROP TABLE position');
        $this->addSql('DROP INDEX IDX_2CEBC65119BBD3D');
    }
}

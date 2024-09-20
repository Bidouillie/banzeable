<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240919034750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pgn ALTER event DROP NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER site DROP NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER date DROP NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER round DROP NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER white DROP NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER black DROP NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER result DROP NOT NULL');
        $this->addSql('ALTER TABLE variation DROP CONSTRAINT fk_629b33ead30b9b2d');
        $this->addSql('DROP INDEX uniq_629b33ead30b9b2d');
        $this->addSql('ALTER TABLE variation ADD event VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE variation ADD site VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE variation ADD date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE variation ADD round VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE variation ADD white VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE variation ADD black VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE variation ADD result VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE variation DROP pgn_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pgn ALTER event SET NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER site SET NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER date SET NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER round SET NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER white SET NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER black SET NOT NULL');
        $this->addSql('ALTER TABLE pgn ALTER result SET NOT NULL');
        $this->addSql('ALTER TABLE variation ADD pgn_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE variation DROP event');
        $this->addSql('ALTER TABLE variation DROP site');
        $this->addSql('ALTER TABLE variation DROP date');
        $this->addSql('ALTER TABLE variation DROP round');
        $this->addSql('ALTER TABLE variation DROP white');
        $this->addSql('ALTER TABLE variation DROP black');
        $this->addSql('ALTER TABLE variation DROP result');
        $this->addSql('ALTER TABLE variation ADD CONSTRAINT fk_629b33ead30b9b2d FOREIGN KEY (pgn_id) REFERENCES pgn (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_629b33ead30b9b2d ON variation (pgn_id)');
    }
}

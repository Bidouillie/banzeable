<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250125043024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE move_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE variation_move_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE variation_move (id INT NOT NULL, variation_id INT NOT NULL, notation_id INT NOT NULL, selected_multiplier DOUBLE PRECISION DEFAULT NULL, total_selected_multiplier DOUBLE PRECISION DEFAULT NULL, fen_reached VARCHAR(255) NOT NULL, coverage DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_946C91665182BFD8 ON variation_move (variation_id)');
        $this->addSql('CREATE INDEX IDX_946C91669680B7F7 ON variation_move (notation_id)');
        $this->addSql('CREATE TABLE variation_move_notation (variation_move_id INT NOT NULL, notation_id INT NOT NULL, PRIMARY KEY(variation_move_id, notation_id))');
        $this->addSql('CREATE INDEX IDX_2A1AC770645E41E ON variation_move_notation (variation_move_id)');
        $this->addSql('CREATE INDEX IDX_2A1AC7709680B7F7 ON variation_move_notation (notation_id)');

        $this->addSql('INSERT INTO variation_move_notation (variation_move_id, notation_id) SELECT move_id, notation_id FROM move_notation');
        $this->addSql('INSERT INTO variation_move (id, variation_id, notation_id, selected_multiplier, total_selected_multiplier, fen_reached, coverage) SELECT NEXTVAL(\'variation_move_id_seq\'), variation_id, notation_id, selected_multiplier, total_selected_multiplier, fen_reached, coverage FROM move');

        $this->addSql('ALTER TABLE variation_move ADD CONSTRAINT FK_946C91665182BFD8 FOREIGN KEY (variation_id) REFERENCES variation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE variation_move ADD CONSTRAINT FK_946C91669680B7F7 FOREIGN KEY (notation_id) REFERENCES notation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE variation_move_notation ADD CONSTRAINT FK_2A1AC770645E41E FOREIGN KEY (variation_move_id) REFERENCES variation_move (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE variation_move_notation ADD CONSTRAINT FK_2A1AC7709680B7F7 FOREIGN KEY (notation_id) REFERENCES notation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE move_notation DROP CONSTRAINT fk_f795b5046dc541a8');
        $this->addSql('ALTER TABLE move_notation DROP CONSTRAINT fk_f795b5049680b7f7');
        $this->addSql('ALTER TABLE move DROP CONSTRAINT fk_ef3e37785182bfd8');
        $this->addSql('ALTER TABLE move DROP CONSTRAINT fk_ef3e37789680b7f7');
        $this->addSql('DROP TABLE move_notation');
        $this->addSql('DROP TABLE move');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE variation_move_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE move_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE move_notation (move_id INT NOT NULL, notation_id INT NOT NULL, PRIMARY KEY(move_id, notation_id))');
        $this->addSql('CREATE INDEX idx_f795b5049680b7f7 ON move_notation (notation_id)');
        $this->addSql('CREATE INDEX idx_f795b5046dc541a8 ON move_notation (move_id)');
        $this->addSql('CREATE TABLE move (id INT NOT NULL, variation_id INT NOT NULL, notation_id INT NOT NULL, selected_multiplier DOUBLE PRECISION DEFAULT NULL, fen_reached VARCHAR(255) NOT NULL, total_selected_multiplier DOUBLE PRECISION DEFAULT NULL, coverage DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_ef3e37789680b7f7 ON move (notation_id)');
        $this->addSql('CREATE INDEX idx_ef3e37785182bfd8 ON move (variation_id)');

        $this->addSql('INSERT INTO move_notation (move_id, notation_id) SELECT variation_move_id, notation_id FROM variation_move_notation');
        $this->addSql('INSERT INTO move (id, variation_id, notation_id, selected_multiplier, total_selected_multiplier, fen_reached, coverage) SELECT NEXTVAL(\'move_id_seq\'), variation_id, notation_id, selected_multiplier, total_selected_multiplier, fen_reached, coverage FROM variation_move');

        $this->addSql('ALTER TABLE move_notation ADD CONSTRAINT fk_f795b5046dc541a8 FOREIGN KEY (move_id) REFERENCES move (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE move_notation ADD CONSTRAINT fk_f795b5049680b7f7 FOREIGN KEY (notation_id) REFERENCES notation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE move ADD CONSTRAINT fk_ef3e37785182bfd8 FOREIGN KEY (variation_id) REFERENCES variation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE move ADD CONSTRAINT fk_ef3e37789680b7f7 FOREIGN KEY (notation_id) REFERENCES notation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE variation_move DROP CONSTRAINT FK_946C91665182BFD8');
        $this->addSql('ALTER TABLE variation_move DROP CONSTRAINT FK_946C91669680B7F7');
        $this->addSql('ALTER TABLE variation_move_notation DROP CONSTRAINT FK_2A1AC770645E41E');
        $this->addSql('ALTER TABLE variation_move_notation DROP CONSTRAINT FK_2A1AC7709680B7F7');
        $this->addSql('DROP TABLE variation_move');
        $this->addSql('DROP TABLE variation_move_notation');
    }
}

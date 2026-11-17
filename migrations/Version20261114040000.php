<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Formateurs : candidatures, rôle formateur sur le compte, auteur des formations. */
final class Version20261114040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Candidatures formateurs, utilisateur.formateur, formation.auteur_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE demande_formateur (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, nom VARCHAR(150) NOT NULL, email VARCHAR(180) NOT NULL, telephone VARCHAR(30) DEFAULT NULL, profession VARCHAR(150) DEFAULT NULL, domaines JSON NOT NULL, formats JSON NOT NULL, annees_experience INT DEFAULT NULL, lien_exemple VARCHAR(255) DEFAULT NULL, message LONGTEXT DEFAULT NULL, mot_de_passe VARCHAR(255) DEFAULT NULL, statut VARCHAR(20) DEFAULT \'nouvelle\' NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_985A849AFB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE demande_formateur ADD CONSTRAINT FK_985A849AFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE utilisateur ADD formateur TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE formation ADD auteur_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_404021BF60BB6FE6 ON formation (auteur_id)');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_formateur DROP FOREIGN KEY FK_985A849AFB88E14F');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF60BB6FE6');
        $this->addSql('DROP INDEX IDX_404021BF60BB6FE6 ON formation');
        $this->addSql('ALTER TABLE formation DROP auteur_id');
        $this->addSql('ALTER TABLE utilisateur DROP formateur');
        $this->addSql('DROP TABLE demande_formateur');
    }
}

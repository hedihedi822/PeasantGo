<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PeasantGo.AI v2 : justificatif de propriété / location des terrains, fenêtre d'accueil en deux parties,
 * saison passée et élevage sur les cultures, alertes du robot par email / SMS.
 */
final class Version20261114030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Justificatifs des terrains, questionnaire, saison passée et élevage, alertes du robot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE alerte_estimation (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, code_terrain INT DEFAULT NULL, created_at DATETIME NOT NULL, cle VARCHAR(150) NOT NULL, titre VARCHAR(150) NOT NULL, message LONGTEXT NOT NULL, canaux JSON NOT NULL, INDEX IDX_CB558385FB88E14F (utilisateur_id), INDEX IDX_CB558385F0D1172D (code_terrain), INDEX IDX_CB558385FB88E14F41401D17 (utilisateur_id, cle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE code_terrain ADD justificatif_type VARCHAR(20) DEFAULT NULL, ADD justificatif_fichier VARCHAR(255) DEFAULT NULL, ADD justificatif_mime VARCHAR(60) DEFAULT NULL, ADD justificatif_statut VARCHAR(20) DEFAULT \'a_fournir\' NOT NULL, ADD justificatif_verification JSON DEFAULT NULL, ADD justificatif_le DATETIME DEFAULT NULL, ADD contour_approximatif TINYINT(1) DEFAULT 0 NOT NULL, ADD questionnaire JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE cultivation ADD origine VARCHAR(20) DEFAULT \'formulaire\' NOT NULL, ADD effectif INT DEFAULT NULL, ADD irrigue TINYINT(1) DEFAULT NULL, ADD detail_couts JSON DEFAULT NULL, ADD canal_vente VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE alerte_estimation ADD CONSTRAINT FK_CB558385FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE alerte_estimation ADD CONSTRAINT FK_CB558385F0D1172D FOREIGN KEY (code_terrain) REFERENCES code_terrain (code_terrain) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE alerte_estimation DROP FOREIGN KEY FK_CB558385FB88E14F');
        $this->addSql('ALTER TABLE alerte_estimation DROP FOREIGN KEY FK_CB558385F0D1172D');
        $this->addSql('DROP TABLE alerte_estimation');
        $this->addSql('ALTER TABLE code_terrain DROP justificatif_type, DROP justificatif_fichier, DROP justificatif_mime, DROP justificatif_statut, DROP justificatif_verification, DROP justificatif_le, DROP contour_approximatif, DROP questionnaire');
        $this->addSql('ALTER TABLE cultivation DROP origine, DROP effectif, DROP irrigue, DROP detail_couts, DROP canal_vente');
    }
}

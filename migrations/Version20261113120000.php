<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Robot PeasantGo.AI : zones, statistiques par zone, prix observés, prix des intrants, veille des sites,
 * estimations par terrain, analyses IA, apprentissage ; localisation des terrains ; récolte déclarée.
 */
final class Version20261113120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Robot PeasantGo.AI (zones, estimations, veille, apprentissage) + récolte des cultivations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE source_veille (id INT AUTO_INCREMENT NOT NULL, zone_id INT DEFAULT NULL, nom VARCHAR(160) NOT NULL, url VARCHAR(500) NOT NULL, type VARCHAR(20) NOT NULL, mots_cles VARCHAR(255) DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, derniere_visite DATETIME DEFAULT NULL, dernier_resultat VARCHAR(255) DEFAULT NULL, INDEX IDX_78E7A9159F2C3FAB (zone_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE prix_marche (id INT AUTO_INCREMENT NOT NULL, zone_id INT DEFAULT NULL, culture_cle VARCHAR(60) NOT NULL, lieu_texte VARCHAR(160) DEFAULT NULL, date_prix DATE NOT NULL, prix_kg DOUBLE PRECISION NOT NULL, type VARCHAR(20) NOT NULL, source VARCHAR(20) NOT NULL, source_url VARCHAR(500) DEFAULT NULL, INDEX IDX_3692B9519F2C3FAB (zone_id), INDEX idx_prix_culture_date (culture_cle, date_prix), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE modele_calibration (id INT AUTO_INCREMENT NOT NULL, zone_id INT DEFAULT NULL, culture_cle VARCHAR(60) NOT NULL, nb_recoltes INT NOT NULL, nb_reussites INT NOT NULL, rendement_moyen DOUBLE PRECISION DEFAULT NULL, facteur_rendement DOUBLE PRECISION NOT NULL, facteur_prix DOUBLE PRECISION NOT NULL, nb_prix INT DEFAULT 0 NOT NULL, facteur_cout DOUBLE PRECISION DEFAULT \'1\' NOT NULL, nb_couts INT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_368B218E9F2C3FAB (zone_id), UNIQUE INDEX uniq_calibration (culture_cle, zone_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE analyse_ia (id INT AUTO_INCREMENT NOT NULL, code_terrain INT NOT NULL, type VARCHAR(20) NOT NULL, resume LONGTEXT NOT NULL, details JSON NOT NULL, modele VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_6E94F230F0D1172D (code_terrain), INDEX idx_analyse_terrain_type (code_terrain, type, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE zone (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, niveau VARCHAR(20) NOT NULL, code VARCHAR(80) NOT NULL, nom VARCHAR(120) NOT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, population BIGINT DEFAULT NULL, annee_population SMALLINT DEFAULT NULL, source_population VARCHAR(120) DEFAULT NULL, INDEX IDX_A0EBC007727ACA70 (parent_id), UNIQUE INDEX uniq_zone_niveau_code (niveau, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE zone_stat (id INT AUTO_INCREMENT NOT NULL, zone_id INT NOT NULL, culture_cle VARCHAR(60) NOT NULL, annee SMALLINT NOT NULL, source VARCHAR(20) NOT NULL, production_t DOUBLE PRECISION DEFAULT NULL, surface_ha DOUBLE PRECISION DEFAULT NULL, consommation_kg_hab DOUBLE PRECISION DEFAULT NULL, cout_ha DOUBLE PRECISION DEFAULT NULL, prix_kg DOUBLE PRECISION DEFAULT NULL, source_url VARCHAR(500) DEFAULT NULL, updated_at DATETIME NOT NULL, INDEX IDX_B2403E849F2C3FAB (zone_id), UNIQUE INDEX uniq_zonestat (zone_id, culture_cle, annee, source), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE estimation_terrain (id INT AUTO_INCREMENT NOT NULL, code_terrain INT NOT NULL, lot VARCHAR(20) NOT NULL, culture_cle VARCHAR(60) NOT NULL, culture_nom VARCHAR(100) NOT NULL, numero_saison SMALLINT NOT NULL, saisons_an SMALLINT NOT NULL, debut_cultivation DATE NOT NULL, fin_cultivation DATE NOT NULL, debut_exploitation DATE NOT NULL, fin_exploitation DATE NOT NULL, taux_reussite DOUBLE PRECISION NOT NULL, production_estimee DOUBLE PRECISION NOT NULL, exportation TINYINT(1) NOT NULL, cout DOUBLE PRECISION NOT NULL, prix DOUBLE PRECISION NOT NULL, benefice DOUBLE PRECISION NOT NULL, recommande TINYINT(1) DEFAULT 0 NOT NULL, details JSON NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_E5D9D8EEF0D1172D (code_terrain), INDEX idx_estimation_terrain_lot (code_terrain, lot), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE prix_intrant (id INT AUTO_INCREMENT NOT NULL, zone_id INT DEFAULT NULL, intrant VARCHAR(40) NOT NULL, prix DOUBLE PRECISION NOT NULL, date_prix DATE NOT NULL, source VARCHAR(20) NOT NULL, source_url VARCHAR(500) DEFAULT NULL, vendeur VARCHAR(160) DEFAULT NULL, INDEX IDX_17E918AB9F2C3FAB (zone_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE page_veille (id INT AUTO_INCREMENT NOT NULL, source_id INT NOT NULL, url VARCHAR(1000) NOT NULL, url_hash VARCHAR(64) NOT NULL, contenu_hash VARCHAR(64) NOT NULL, nb_extraits INT DEFAULT 0 NOT NULL, lue_le DATETIME NOT NULL, INDEX IDX_A1CE7AED953C1C61 (source_id), UNIQUE INDEX uniq_page_veille_url (url_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE code_terrain ADD localite_id INT DEFAULT NULL, ADD gouvernorat_id INT DEFAULT NULL, ADD latitude DOUBLE PRECISION DEFAULT NULL, ADD longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE cultivation ADD quantite_recoltee DOUBLE PRECISION DEFAULT NULL, ADD prix_vente DOUBLE PRECISION DEFAULT NULL, ADD cout_reel DOUBLE PRECISION DEFAULT NULL, ADD date_recolte DATE DEFAULT NULL, ADD resultat VARCHAR(20) DEFAULT NULL, ADD remarque_recolte LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_F0D1172D924DD2B5 ON code_terrain (localite_id)');
        $this->addSql('CREATE INDEX IDX_F0D1172D75B74E2D ON code_terrain (gouvernorat_id)');
        $this->addSql('ALTER TABLE code_terrain ADD CONSTRAINT FK_F0D1172D924DD2B5 FOREIGN KEY (localite_id) REFERENCES zone (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE code_terrain ADD CONSTRAINT FK_F0D1172D75B74E2D FOREIGN KEY (gouvernorat_id) REFERENCES zone (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE source_veille ADD CONSTRAINT FK_78E7A9159F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE prix_marche ADD CONSTRAINT FK_3692B9519F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE modele_calibration ADD CONSTRAINT FK_368B218E9F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE analyse_ia ADD CONSTRAINT FK_6E94F230F0D1172D FOREIGN KEY (code_terrain) REFERENCES code_terrain (code_terrain) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE zone ADD CONSTRAINT FK_A0EBC007727ACA70 FOREIGN KEY (parent_id) REFERENCES zone (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE zone_stat ADD CONSTRAINT FK_B2403E849F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE estimation_terrain ADD CONSTRAINT FK_E5D9D8EEF0D1172D FOREIGN KEY (code_terrain) REFERENCES code_terrain (code_terrain) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE prix_intrant ADD CONSTRAINT FK_17E918AB9F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE page_veille ADD CONSTRAINT FK_A1CE7AED953C1C61 FOREIGN KEY (source_id) REFERENCES source_veille (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE code_terrain DROP FOREIGN KEY FK_F0D1172D924DD2B5');
        $this->addSql('ALTER TABLE code_terrain DROP FOREIGN KEY FK_F0D1172D75B74E2D');
        $this->addSql('ALTER TABLE source_veille DROP FOREIGN KEY FK_78E7A9159F2C3FAB');
        $this->addSql('ALTER TABLE prix_marche DROP FOREIGN KEY FK_3692B9519F2C3FAB');
        $this->addSql('ALTER TABLE modele_calibration DROP FOREIGN KEY FK_368B218E9F2C3FAB');
        $this->addSql('ALTER TABLE analyse_ia DROP FOREIGN KEY FK_6E94F230F0D1172D');
        $this->addSql('ALTER TABLE zone DROP FOREIGN KEY FK_A0EBC007727ACA70');
        $this->addSql('ALTER TABLE zone_stat DROP FOREIGN KEY FK_B2403E849F2C3FAB');
        $this->addSql('ALTER TABLE estimation_terrain DROP FOREIGN KEY FK_E5D9D8EEF0D1172D');
        $this->addSql('ALTER TABLE prix_intrant DROP FOREIGN KEY FK_17E918AB9F2C3FAB');
        $this->addSql('ALTER TABLE page_veille DROP FOREIGN KEY FK_A1CE7AED953C1C61');
        $this->addSql('ALTER TABLE code_terrain DROP localite_id, DROP gouvernorat_id, DROP latitude, DROP longitude');
        $this->addSql('ALTER TABLE cultivation DROP quantite_recoltee, DROP prix_vente, DROP cout_reel, DROP date_recolte, DROP resultat, DROP remarque_recolte');
        $this->addSql('DROP TABLE zone_stat');
        $this->addSql('DROP TABLE zone');
        $this->addSql('DROP TABLE source_veille');
        $this->addSql('DROP TABLE prix_marche');
        $this->addSql('DROP TABLE prix_intrant');
        $this->addSql('DROP TABLE page_veille');
        $this->addSql('DROP TABLE modele_calibration');
        $this->addSql('DROP TABLE estimation_terrain');
        $this->addSql('DROP TABLE analyse_ia');
    }
}

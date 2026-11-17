<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma complet de PeasantGoGo (MySQL 8 / MariaDB 10.5+).
 * Appliquer avec : php bin/console doctrine:migrations:migrate
 */
final class Version20261112000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial PeasantGoGo : utilisateurs, terrains, compteurs, cultures, catalogue agricole, formations, partenariats, messages, favoris';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform, 'Migration prévue pour MySQL / MariaDB.');

        $this->addSql('CREATE TABLE code_compteurs (code_compteur INT AUTO_INCREMENT NOT NULL, code_terrain INT NOT NULL, numero VARCHAR(60) NOT NULL, type VARCHAR(60) NOT NULL, en_fonctionnement TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_41BF9373F0D1172D (code_terrain), PRIMARY KEY(code_compteur)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE code_terrain (code_terrain INT AUTO_INCREMENT NOT NULL, proprietaire_id INT NOT NULL, nom VARCHAR(120) NOT NULL, localisation VARCHAR(255) DEFAULT NULL, code_postal INT DEFAULT NULL, surface DOUBLE PRECISION NOT NULL, en_exploitation TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_F0D1172D76C50E4A (proprietaire_id), PRIMARY KEY(code_terrain)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cultivation (id INT AUTO_INCREMENT NOT NULL, code_terrain INT NOT NULL, code_compteur INT DEFAULT NULL, id_p INT DEFAULT NULL, culture_cle VARCHAR(60) NOT NULL, culture_nom VARCHAR(100) NOT NULL, surface DOUBLE PRECISION NOT NULL, date_debut_cultivation DATE NOT NULL, date_debut_exploitation DATE DEFAULT NULL, statut VARCHAR(20) NOT NULL, actif TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_ACAFA797B48363FE (code_compteur), INDEX IDX_ACAFA797F0D1172D (code_terrain), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE production (id_p INT NOT NULL, id_s INT NOT NULL, production DOUBLE PRECISION NOT NULL, production_par_etat DOUBLE PRECISION NOT NULL, production_par_region DOUBLE PRECISION NOT NULL, PRIMARY KEY(id_p, id_s)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE etat_production (id_p INT NOT NULL, id_s INT NOT NULL, exce_production TINYINT(1) NOT NULL, pourcentage_exce DOUBLE PRECISION NOT NULL, PRIMARY KEY(id_p, id_s)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ferme (id INT AUTO_INCREMENT NOT NULL, code_terrain INT NOT NULL, localisation VARCHAR(255) NOT NULL, type VARCHAR(120) NOT NULL, INDEX IDX_66564EC2F0D1172D (code_terrain), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, telephone VARCHAR(20) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, cin INT DEFAULT NULL, nom VARCHAR(100) DEFAULT NULL, prenom VARCHAR(100) DEFAULT NULL, adresse LONGTEXT DEFAULT NULL, date_naissance DATE DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, preferences JSON NOT NULL, newsletter TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, reset_code_hash VARCHAR(255) DEFAULT NULL, reset_code_expires_at DATETIME DEFAULT NULL, reset_attempts SMALLINT DEFAULT 0 NOT NULL, type VARCHAR(20) NOT NULL, nom_societe VARCHAR(35) DEFAULT NULL, code_fiscal VARCHAR(20) DEFAULT NULL, numero_siret VARCHAR(14) DEFAULT NULL, code_societe VARCHAR(20) DEFAULT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), UNIQUE INDEX UNIQ_1D1C63B3ABE530DA (cin), UNIQUE INDEX UNIQ_1D1C63B38F320DE4 (code_fiscal), UNIQUE INDEX UNIQ_1D1C63B389B6684F (numero_siret), UNIQUE INDEX UNIQ_1D1C63B321C27921 (code_societe), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE favori (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, id_p INT NOT NULL, INDEX IDX_EF85A2CCFB88E14F (utilisateur_id), UNIQUE INDEX uniq_favori_user_produit (utilisateur_id, id_p), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE demande_partenariat (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, telephone VARCHAR(30) DEFAULT NULL, facebook VARCHAR(255) DEFAULT NULL, instagram VARCHAR(255) DEFAULT NULL, whatsapp VARCHAR(30) DEFAULT NULL, autre_contact VARCHAR(255) DEFAULT NULL, methodes_aide JSON NOT NULL, autre_aide LONGTEXT DEFAULT NULL, services_gratuits JSON NOT NULL, a_propos LONGTEXT DEFAULT NULL, source VARCHAR(30) DEFAULT NULL, statut VARCHAR(20) DEFAULT \'nouvelle\' NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_1E7483F2FB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE taux_reussite (id_p INT NOT NULL, id_s INT NOT NULL, taux DOUBLE PRECISION NOT NULL, taux_etat DOUBLE PRECISION NOT NULL, taux_region DOUBLE PRECISION NOT NULL, PRIMARY KEY(id_p, id_s)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cout (id_p INT NOT NULL, id_s INT NOT NULL, besoins_par_hectare LONGTEXT NOT NULL, cout_essentiel_hectare DOUBLE PRECISION NOT NULL, cout_total_hectare DOUBLE PRECISION NOT NULL, PRIMARY KEY(id_p, id_s)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE exportation (id_p INT NOT NULL, id_s INT NOT NULL, export_exist TINYINT(1) NOT NULL, quantite_exporte DOUBLE PRECISION NOT NULL, societe_export VARCHAR(255) NOT NULL, PRIMARY KEY(id_p, id_s)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE terre_designee (id INT AUTO_INCREMENT NOT NULL, id_p INT NOT NULL, id_s INT NOT NULL, pourcentage_terre_designee DOUBLE PRECISION NOT NULL, pourcentage_terre_designee_dans_etat DOUBLE PRECISION NOT NULL, pourcentage_terre_designee_dans_region DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE region (code_region INT NOT NULL, etat VARCHAR(255) NOT NULL, nom_region VARCHAR(255) NOT NULL, code_postal INT NOT NULL, annee INT NOT NULL, bebe INT NOT NULL, enfant INT NOT NULL, adolescent INT NOT NULL, jeune INT NOT NULL, adulte INT NOT NULL, agee INT NOT NULL, croissance_demog DOUBLE PRECISION NOT NULL, id_p INT NOT NULL, consommation_jour DOUBLE PRECISION NOT NULL, PRIMARY KEY(code_region)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE prix (id_p INT NOT NULL, id_s INT NOT NULL, prix_moyenne_exportation DOUBLE PRECISION NOT NULL, prix_moyenne_de_gros_par_etat DOUBLE PRECISION NOT NULL, prix_moyenne_de_gros_par_region DOUBLE PRECISION NOT NULL, PRIMARY KEY(id_p, id_s)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE machine (id INT AUTO_INCREMENT NOT NULL, proprietaire_id INT NOT NULL, type VARCHAR(120) NOT NULL, annee SMALLINT NOT NULL, INDEX IDX_1505DF8476C50E4A (proprietaire_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE date_cultivation (id_p INT NOT NULL, id_s INT NOT NULL, date_debut_cultivation DATETIME NOT NULL, date_debut_cultivation_etat DATETIME NOT NULL, date_debut_cultivation_region DATETIME NOT NULL, date_debut_exploitation DATETIME NOT NULL, date_debut_exploitation_etat DATETIME NOT NULL, date_debut_exploitation_region DATETIME NOT NULL, PRIMARY KEY(id_p, id_s)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consommation (id_p INT NOT NULL, id_s INT NOT NULL, consommation DOUBLE PRECISION NOT NULL, consommation_par_etat DOUBLE PRECISION NOT NULL, consommation_par_region DOUBLE PRECISION NOT NULL, PRIMARY KEY(id_p, id_s)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE saison (id_s INT NOT NULL, id_p INT NOT NULL, annee INT NOT NULL, numero_saison_par_an INT NOT NULL, PRIMARY KEY(id_s)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE message_contact (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, sujet VARCHAR(20) NOT NULL, priorite VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, traite TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_DCEADC34FB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE outils (id_o INT AUTO_INCREMENT NOT NULL, nom_outils VARCHAR(255) NOT NULL, importance_outils VARCHAR(255) NOT NULL, prix_outils DOUBLE PRECISION NOT NULL, PRIMARY KEY(id_o)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE produits_agricole (id_p INT NOT NULL, type VARCHAR(100) NOT NULL, sous_type VARCHAR(100) NOT NULL, categorie VARCHAR(100) NOT NULL, methode_de_cultivation VARCHAR(150) NOT NULL, nombre_saison_total_par_an INT NOT NULL, productivite_par_hectare INT NOT NULL, pourcentage_confiance_estimation DOUBLE PRECISION NOT NULL, pourcentage_confiance_estimation_par_etat DOUBLE PRECISION NOT NULL, pourcentage_confiance_estimation_par_region DOUBLE PRECISION NOT NULL, exce_production TINYINT(1) NOT NULL, PRIMARY KEY(id_p)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, sous_type VARCHAR(255) NOT NULL, categorie VARCHAR(255) NOT NULL, formation_video VARCHAR(255) DEFAULT NULL, formation_pdf VARCHAR(255) DEFAULT NULL, formation_word VARCHAR(255) DEFAULT NULL, title VARCHAR(500) DEFAULT NULL, description LONGTEXT DEFAULT NULL, difficulty VARCHAR(50) DEFAULT NULL, duration INT DEFAULT NULL, views INT DEFAULT 0 NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, tags JSON DEFAULT NULL, average_rating NUMERIC(3, 2) DEFAULT NULL, rating_count INT DEFAULT 0 NOT NULL, poster_path VARCHAR(500) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE code_compteurs ADD CONSTRAINT FK_41BF9373F0D1172D FOREIGN KEY (code_terrain) REFERENCES code_terrain (code_terrain) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE code_terrain ADD CONSTRAINT FK_F0D1172D76C50E4A FOREIGN KEY (proprietaire_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cultivation ADD CONSTRAINT FK_ACAFA797F0D1172D FOREIGN KEY (code_terrain) REFERENCES code_terrain (code_terrain) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cultivation ADD CONSTRAINT FK_ACAFA797B48363FE FOREIGN KEY (code_compteur) REFERENCES code_compteurs (code_compteur) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ferme ADD CONSTRAINT FK_66564EC2F0D1172D FOREIGN KEY (code_terrain) REFERENCES code_terrain (code_terrain) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favori ADD CONSTRAINT FK_EF85A2CCFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE demande_partenariat ADD CONSTRAINT FK_1E7483F2FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_1505DF8476C50E4A FOREIGN KEY (proprietaire_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_contact ADD CONSTRAINT FK_DCEADC34FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE code_compteurs DROP FOREIGN KEY FK_41BF9373F0D1172D');
        $this->addSql('ALTER TABLE code_terrain DROP FOREIGN KEY FK_F0D1172D76C50E4A');
        $this->addSql('ALTER TABLE cultivation DROP FOREIGN KEY FK_ACAFA797F0D1172D');
        $this->addSql('ALTER TABLE cultivation DROP FOREIGN KEY FK_ACAFA797B48363FE');
        $this->addSql('ALTER TABLE ferme DROP FOREIGN KEY FK_66564EC2F0D1172D');
        $this->addSql('ALTER TABLE favori DROP FOREIGN KEY FK_EF85A2CCFB88E14F');
        $this->addSql('ALTER TABLE demande_partenariat DROP FOREIGN KEY FK_1E7483F2FB88E14F');
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_1505DF8476C50E4A');
        $this->addSql('ALTER TABLE message_contact DROP FOREIGN KEY FK_DCEADC34FB88E14F');
        $this->addSql('DROP TABLE code_compteurs');
        $this->addSql('DROP TABLE code_terrain');
        $this->addSql('DROP TABLE cultivation');
        $this->addSql('DROP TABLE production');
        $this->addSql('DROP TABLE etat_production');
        $this->addSql('DROP TABLE ferme');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE favori');
        $this->addSql('DROP TABLE demande_partenariat');
        $this->addSql('DROP TABLE taux_reussite');
        $this->addSql('DROP TABLE cout');
        $this->addSql('DROP TABLE exportation');
        $this->addSql('DROP TABLE terre_designee');
        $this->addSql('DROP TABLE region');
        $this->addSql('DROP TABLE prix');
        $this->addSql('DROP TABLE machine');
        $this->addSql('DROP TABLE date_cultivation');
        $this->addSql('DROP TABLE consommation');
        $this->addSql('DROP TABLE saison');
        $this->addSql('DROP TABLE message_contact');
        $this->addSql('DROP TABLE outils');
        $this->addSql('DROP TABLE produits_agricole');
        $this->addSql('DROP TABLE formation');
        $this->addSql('DROP TABLE messenger_messages');
    }
}

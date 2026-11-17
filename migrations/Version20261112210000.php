<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Notifications pour tous, historique des statistiques, images satellites et reconnaissance des cultures. */
final class Version20261112210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notifications, snapshots de statistiques, contour GPS des terrains, observations satellites, prédictions de cultures';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform, 'Migration prévue pour MySQL / MariaDB.');

        $this->addSql('ALTER TABLE code_terrain ADD geometrie JSON DEFAULT NULL, ADD agro_polygon_id VARCHAR(64) DEFAULT NULL, ADD satellite_sync_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, type VARCHAR(20) NOT NULL, titre VARCHAR(150) NOT NULL, message LONGTEXT NOT NULL, lien VARCHAR(255) DEFAULT NULL, lue TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_BF5476CAFB88E14F (utilisateur_id), INDEX idx_notification_user_lue (utilisateur_id, lue), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE observation_satellite (id INT AUTO_INCREMENT NOT NULL, code_terrain INT NOT NULL, source VARCHAR(20) NOT NULL, date_observation DATE NOT NULL, ndvi DOUBLE PRECISION DEFAULT NULL, ndvi_min DOUBLE PRECISION DEFAULT NULL, ndvi_max DOUBLE PRECISION DEFAULT NULL, evi DOUBLE PRECISION DEFAULT NULL, ndmi DOUBLE PRECISION DEFAULT NULL, nuages DOUBLE PRECISION DEFAULT NULL, image_url VARCHAR(500) DEFAULT NULL, ndvi_image_url VARCHAR(500) DEFAULT NULL, INDEX IDX_77F17184F0D1172D (code_terrain), UNIQUE INDEX uniq_obs_terrain_source_date (code_terrain, source, date_observation), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE statistique_snapshot (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, donnees JSON NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE prediction_culture (id INT AUTO_INCREMENT NOT NULL, code_terrain INT NOT NULL, culture_cle VARCHAR(60) NOT NULL, culture_nom VARCHAR(100) NOT NULL, confiance DOUBLE PRECISION NOT NULL, probabilites JSON NOT NULL, nb_observations SMALLINT NOT NULL, periode_debut DATE DEFAULT NULL, periode_fin DATE DEFAULT NULL, culture_declaree VARCHAR(60) DEFAULT NULL, conforme TINYINT(1) DEFAULT NULL, nb_exemples INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_1B48065BF0D1172D (code_terrain), INDEX idx_prediction_terrain_date (code_terrain, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE observation_satellite ADD CONSTRAINT FK_77F17184F0D1172D FOREIGN KEY (code_terrain) REFERENCES code_terrain (code_terrain) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE prediction_culture ADD CONSTRAINT FK_1B48065BF0D1172D FOREIGN KEY (code_terrain) REFERENCES code_terrain (code_terrain) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAFB88E14F');
        $this->addSql('ALTER TABLE observation_satellite DROP FOREIGN KEY FK_77F17184F0D1172D');
        $this->addSql('ALTER TABLE prediction_culture DROP FOREIGN KEY FK_1B48065BF0D1172D');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE statistique_snapshot');
        $this->addSql('DROP TABLE observation_satellite');
        $this->addSql('DROP TABLE prediction_culture');
        $this->addSql('ALTER TABLE code_terrain DROP geometrie, DROP agro_polygon_id, DROP satellite_sync_at');
    }
}

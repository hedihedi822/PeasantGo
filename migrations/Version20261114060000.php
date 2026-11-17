<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Achats de formations payantes (liés au compte de l'agriculteur). */
final class Version20261114060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table achat_formation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE achat_formation (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, formation_id INT NOT NULL, prix NUMERIC(8, 3) NOT NULL, statut VARCHAR(20) NOT NULL, moyen VARCHAR(20) DEFAULT NULL, reference VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL, payee_le DATETIME DEFAULT NULL, INDEX IDX_ACHAT_UTIL (utilisateur_id), INDEX IDX_ACHAT_FORM (formation_id), UNIQUE INDEX achat_unique (utilisateur_id, formation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE achat_formation ADD CONSTRAINT FK_ACHAT_UTIL FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE achat_formation ADD CONSTRAINT FK_ACHAT_FORM FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE achat_formation');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Formations payantes ou gratuites, avec prix fixé par le formateur. */
final class Version20261114050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'formation.payante, formation.prix';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formation ADD payante TINYINT(1) DEFAULT 0 NOT NULL, ADD prix NUMERIC(8, 3) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formation DROP payante, DROP prix');
    }
}

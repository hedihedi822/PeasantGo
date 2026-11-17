<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Membre de l'équipe PeasantGo : accès au panneau d'administration.
 */
#[ORM\Entity]
class Admin extends Utilisateur
{
    public function getTypeCompte(): string
    {
        return 'admin';
    }

    public function getRoles(): array
    {
        return ['ROLE_USER', 'ROLE_ADMIN'];
    }
}

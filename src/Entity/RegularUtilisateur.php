<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Personne (agriculteur). Se connecte avec son email.
 * Les champs cin / nom / prenom sont hérités de Utilisateur.
 */
#[ORM\Entity]
class RegularUtilisateur extends Utilisateur
{
    public function getTypeCompte(): string
    {
        return 'regular';
    }
}

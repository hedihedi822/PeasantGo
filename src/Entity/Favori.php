<?php

namespace App\Entity;

use App\Repository\FavoriRepository;
use Doctrine\ORM\Mapping as ORM;

/** Produit agricole marqué en favori (étoile) par un utilisateur sur la page d'accueil. */
#[ORM\Entity(repositoryClass: FavoriRepository::class)]
#[ORM\Table(name: 'favori')]
#[ORM\UniqueConstraint(name: 'uniq_favori_user_produit', columns: ['utilisateur_id', 'id_p'])]
class Favori
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(name: 'id_p', type: 'integer')]
    private int $idP = 0;

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(Utilisateur $u): self { $this->utilisateur = $u; return $this; }
    public function getIdP(): int { return $this->idP; }
    public function setIdP(int $idP): self { $this->idP = $idP; return $this; }
}

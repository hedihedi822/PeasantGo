<?php

namespace App\Entity;

use App\Repository\MachineRepository;
use Doctrine\ORM\Mapping as ORM;

/** Machine agricole déclarée par un utilisateur (onglet "Profil Agricole"). */
#[ORM\Entity(repositoryClass: MachineRepository::class)]
#[ORM\Table(name: 'machine')]
class Machine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'machines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $proprietaire = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $type = '';

    #[ORM\Column(type: 'smallint')]
    private int $annee = 2000;

    public function getId(): ?int { return $this->id; }
    public function getProprietaire(): ?Utilisateur { return $this->proprietaire; }
    public function setProprietaire(Utilisateur $u): self { $this->proprietaire = $u; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getAnnee(): int { return $this->annee; }
    public function setAnnee(int $annee): self { $this->annee = $annee; return $this; }
}

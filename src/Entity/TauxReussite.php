<?php

namespace App\Entity;

use App\Repository\TauxReussiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TauxReussiteRepository::class)]
#[ORM\Table(name: 'taux_reussite')]
class TauxReussite
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $idP;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $idS;

    #[ORM\Column(type: 'float')]
    private float $taux;

    #[ORM\Column(type: 'float')]
    private float $tauxEtat;

    #[ORM\Column(type: 'float')]
    private float $tauxRegion;

    public function getIdP(): int { return $this->idP; }
    public function setIdP(int $idP): self { $this->idP = $idP; return $this; }

    public function getIdS(): int { return $this->idS; }
    public function setIdS(int $idS): self { $this->idS = $idS; return $this; }

    public function getTaux(): float { return $this->taux; }
    public function setTaux(float $taux): self { $this->taux = $taux; return $this; }

    public function getTauxEtat(): float { return $this->tauxEtat; }
    public function setTauxEtat(float $tauxEtat): self { $this->tauxEtat = $tauxEtat; return $this; }

    public function getTauxRegion(): float { return $this->tauxRegion; }
    public function setTauxRegion(float $tauxRegion): self { $this->tauxRegion = $tauxRegion; return $this; }
}


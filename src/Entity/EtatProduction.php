<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\EtatProductionRepository')]
#[ORM\Table(name: 'etat_production')]
class EtatProduction
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_p', type: 'integer')]
    private int $idP;

    #[ORM\Id]
    #[ORM\Column(name: 'id_s', type: 'integer')]
    private int $idS;

    #[ORM\Column(name: 'exce_production', type: 'boolean')]
    private bool $exceProduction;

    #[ORM\Column(name: 'pourcentage_exce', type: 'float')]
    private float $pourcenatgeExce;

    // Getters et setters

    public function getIdP(): int
    {
        return $this->idP;
    }

    public function setIdP(int $idP): self
    {
        $this->idP = $idP;
        return $this;
    }

    public function getIdS(): int
    {
        return $this->idS;
    }

    public function setIdS(int $idS): self
    {
        $this->idS = $idS;
        return $this;
    }

    public function isExceProduction(): bool
    {
        return $this->exceProduction;
    }

    public function setExceProduction(bool $exceProduction): self
    {
        $this->exceProduction = $exceProduction;
        return $this;
    }

    public function getPourcenatgeExce(): float
    {
        return $this->pourcenatgeExce;
    }

    public function setPourcenatgeExce(float $pourcenatgeExce): self
    {
        $this->pourcenatgeExce = $pourcenatgeExce;
        return $this;
    }
}

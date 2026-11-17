<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\ConsommationRepository')]
#[ORM\Table(name: 'consommation')]
class Consommation
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_p', type: 'integer')]
    private int $idP;

    #[ORM\Id]
    #[ORM\Column(name: 'id_s', type: 'integer')]
    private int $idS;

    #[ORM\Column(type: 'float')]
    private float $consommation;

    #[ORM\Column(name: 'consommation_par_etat', type: 'float')]
    private float $consommationParEtat;

    #[ORM\Column(name: 'consommation_par_region', type: 'float')]
    private float $consommationParRegion;

    // Getters and setters

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

    public function getConsommation(): float
    {
        return $this->consommation;
    }

    public function setConsommation(float $consommation): self
    {
        $this->consommation = $consommation;
        return $this;
    }

    public function getConsommationParEtat(): float
    {
        return $this->consommationParEtat;
    }

    public function setConsommationParEtat(float $consommationParEtat): self
    {
        $this->consommationParEtat = $consommationParEtat;
        return $this;
    }

    public function getConsommationParRegion(): float
    {
        return $this->consommationParRegion;
    }

    public function setConsommationParRegion(float $consommationParRegion): self
    {
        $this->consommationParRegion = $consommationParRegion;
        return $this;
    }
}
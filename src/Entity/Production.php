<?php

namespace App\Entity;

use App\Repository\ProductionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductionRepository::class)]
class Production
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $idP;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $idS;

    #[ORM\Column(type: 'float')]
    private float $production;

    #[ORM\Column(type: 'float')]
    private float $productionParEtat;

    #[ORM\Column(type: 'float')]
    private float $productionParRegion;

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

    public function getProduction(): float
    {
        return $this->production;
    }

    public function setProduction(float $production): self
    {
        $this->production = $production;
        return $this;
    }

    public function getProductionParEtat(): float
    {
        return $this->productionParEtat;
    }

    public function setProductionParEtat(float $etat): self
    {
        $this->productionParEtat = $etat;
        return $this;
    }

    public function getProductionParRegion(): float
    {
        return $this->productionParRegion;
    }

    public function setProductionParRegion(float $region): self
    {
        $this->productionParRegion = $region;
        return $this;
    }
}


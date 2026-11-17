<?php

namespace App\Entity;

use App\Repository\ProduitsAgricoleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProduitsAgricoleRepository::class)]
class ProduitsAgricole
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $idP;

    #[ORM\Column(length: 100)]
    private string $type;

    #[ORM\Column(length: 100)]
    private string $sousType;

    #[ORM\Column(length: 100)]
    private string $categorie;

    #[ORM\Column(length: 150)]
    private string $methodeDeCultivation;

    #[ORM\Column(type: 'integer')]
    private int $nombreSaisonTotalParAn;

    #[ORM\Column(type: 'integer')]
    private int $productiviteParHectare;

    #[ORM\Column(type: 'float')]
    private float $pourcentageConfianceEstimation;

    #[ORM\Column(type: 'float')]
    private float $pourcentageConfianceEstimationParEtat;

    #[ORM\Column(type: 'float')]
    private float $pourcentageConfianceEstimationParRegion;

    #[ORM\Column(type: 'boolean')]
    private bool $exceProduction;

    // Getters / Setters
    public function getIdP(): int
    {
        return $this->idP;
    }

    public function setIdP(int $idP): self
    {
        $this->idP = $idP;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getSousType(): string
    {
        return $this->sousType;
    }

    public function setSousType(string $sousType): self
    {
        $this->sousType = $sousType;
        return $this;
    }

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getMethodeDeCultivation(): string
    {
        return $this->methodeDeCultivation;
    }

    public function setMethodeDeCultivation(string $methodeDeCultivation): self
    {
        $this->methodeDeCultivation = $methodeDeCultivation;
        return $this;
    }

    public function getNombreSaisonTotalParAn(): int
    {
        return $this->nombreSaisonTotalParAn;
    }

    public function setNombreSaisonTotalParAn(int $val): self
    {
        $this->nombreSaisonTotalParAn = $val;
        return $this;
    }

    public function getProductiviteParHectare(): int
    {
        return $this->productiviteParHectare;
    }

    public function setProductiviteParHectare(int $val): self
    {
        $this->productiviteParHectare = $val;
        return $this;
    }

    public function getPourcentageConfianceEstimation(): float
    {
        return $this->pourcentageConfianceEstimation;
    }

    public function setPourcentageConfianceEstimation(float $val): self
    {
        $this->pourcentageConfianceEstimation = $val;
        return $this;
    }

    public function getPourcentageConfianceEstimationParEtat(): float
    {
        return $this->pourcentageConfianceEstimationParEtat;
    }

    public function setPourcentageConfianceEstimationParEtat(float $val): self
    {
        $this->pourcentageConfianceEstimationParEtat = $val;
        return $this;
    }

    public function getPourcentageConfianceEstimationParRegion(): float
    {
        return $this->pourcentageConfianceEstimationParRegion;
    }

    public function setPourcentageConfianceEstimationParRegion(float $val): self
    {
        $this->pourcentageConfianceEstimationParRegion = $val;
        return $this;
    }

    public function isExceProduction(): bool
    {
        return $this->exceProduction;
    }

    public function setExceProduction(bool $val): self
    {
        $this->exceProduction = $val;
        return $this;
    }
}


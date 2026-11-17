<?php
// src/Entity/Region.php

namespace App\Entity;

use App\Repository\RegionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RegionRepository::class)]
#[ORM\Table(name: 'region')]
class Region
{
    #[ORM\Id]
    #[ORM\Column(name: 'code_region', type: Types::INTEGER)]
    private int $codeRegion;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $etat;

    #[ORM\Column(name: 'nom_region', type: Types::STRING, length: 255)]
    private string $region;

    #[ORM\Column(name: 'code_postal', type: Types::INTEGER)]
    private int $codePostal;

    #[ORM\Column(name: 'annee', type: Types::INTEGER)]
    private int $annee;

    #[ORM\Column(name: 'bebe', type: Types::INTEGER)]
    private int $nombreBebe;

    #[ORM\Column(name: 'enfant', type: Types::INTEGER)]
    private int $nombreEnfant;

    #[ORM\Column(name: 'adolescent', type: Types::INTEGER)]
    private int $nombreAdolescent;

    #[ORM\Column(name: 'jeune', type: Types::INTEGER)]
    private int $nombreJeune;

    #[ORM\Column(name: 'adulte', type: Types::INTEGER)]
    private int $nombreAdulte;

    #[ORM\Column(name: 'agee', type: Types::INTEGER)]
    private int $nombreAgee;

    #[ORM\Column(name: 'croissance_demog', type: Types::FLOAT)]
    private float $pourcentageCroissanceDemog;

    #[ORM\Column(name: 'id_p', type: Types::INTEGER)]
    private int $idP;

    #[ORM\Column(name: 'consommation_jour', type: Types::FLOAT)]
    private float $consommationParJour;

    // Getters et setters
    public function getCodeRegion(): int
    {
        return $this->codeRegion;
    }

    public function setCodeRegion(int $codeRegion): self
    {
        $this->codeRegion = $codeRegion;
        return $this;
    }

    public function getEtat(): string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): self
    {
        $this->etat = $etat;
        return $this;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function setRegion(string $region): self
    {
        $this->region = $region;
        return $this;
    }

    public function getCodePostal(): int
    {
        return $this->codePostal;
    }

    public function setCodePostal(int $codePostal): self
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getNombreBebe(): int
    {
        return $this->nombreBebe;
    }

    public function setNombreBebe(int $nombreBebe): self
    {
        $this->nombreBebe = $nombreBebe;
        return $this;
    }


    public function getAnnee(): int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): self
    {
        $this->annee = $annee;
        return $this;
    }

    public function getNombreEnfant(): int
    {
        return $this->nombreEnfant;
    }

    public function setNombreEnfant(int $nombreEnfant): self
    {
        $this->nombreEnfant = $nombreEnfant;
        return $this;
    }

    public function getNombreAdolescent(): int
    {
        return $this->nombreAdolescent;
    }

    public function setNombreAdolescent(int $nombreAdolescent): self
    {
        $this->nombreAdolescent = $nombreAdolescent;
        return $this;
    }

    public function getNombreJeune(): int
    {
        return $this->nombreJeune;
    }

    public function setNombreJeune(int $nombreJeune): self
    {
        $this->nombreJeune = $nombreJeune;
        return $this;
    }

    public function getNombreAdulte(): int
    {
        return $this->nombreAdulte;
    }

    public function setNombreAdulte(int $nombreAdulte): self
    {
        $this->nombreAdulte = $nombreAdulte;
        return $this;
    }

    public function getNombreAgee(): int
    {
        return $this->nombreAgee;
    }

    public function setNombreAgee(int $nombreAgee): self
    {
        $this->nombreAgee = $nombreAgee;
        return $this;
    }

    public function getPourcentageCroissanceDemog(): float
    {
        return $this->pourcentageCroissanceDemog;
    }

    public function setPourcentageCroissanceDemog(float $pourcentageCroissanceDemog): self
    {
        $this->pourcentageCroissanceDemog = $pourcentageCroissanceDemog;
        return $this;
    }

    public function getIdP(): int
    {
        return $this->idP;
    }

    public function setIdP(int $idP): self
    {
        $this->idP = $idP;
        return $this;
    }

    public function getConsommationParJour(): float
    {
        return $this->consommationParJour;
    }

    public function setConsommationParJour(float $consommationParJour): self
    {
        $this->consommationParJour = $consommationParJour;
        return $this;
    }
}

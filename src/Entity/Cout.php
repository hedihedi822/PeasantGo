<?php
// src/Entity/Cout.php

namespace App\Entity;

use App\Repository\CoutRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CoutRepository::class)]
#[ORM\Table(name: 'cout')]
class Cout
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_p', type: Types::INTEGER)]
    private int $idP;

    #[ORM\Id]
    #[ORM\Column(name: 'id_s', type: Types::INTEGER)]
    private int $idS;

    #[ORM\Column(name: 'besoins_par_hectare', type: Types::TEXT)]
    private string $listDesBesoinsParHectare;

    #[ORM\Column(name: 'cout_essentiel_hectare', type: Types::FLOAT)]
    private float $coutEssentielParHectare;

    #[ORM\Column(name: 'cout_total_hectare', type: Types::FLOAT)]
    private float $coutTotalParHectare;

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

    public function getListDesBesoinsParHectare(): string
    {
        return $this->listDesBesoinsParHectare;
    }

    public function setListDesBesoinsParHectare(string $listDesBesoinsParHectare): self
    {
        $this->listDesBesoinsParHectare = $listDesBesoinsParHectare;
        return $this;
    }

    public function getCoutEssentielParHectare(): float
    {
        return $this->coutEssentielParHectare;
    }

    public function setCoutEssentielParHectare(float $coutEssentielParHectare): self
    {
        $this->coutEssentielParHectare = $coutEssentielParHectare;
        return $this;
    }

    public function getCoutTotalParHectare(): float
    {
        return $this->coutTotalParHectare;
    }

    public function setCoutTotalParHectare(float $coutTotalParHectare): self
    {
        $this->coutTotalParHectare = $coutTotalParHectare;
        return $this;
    }
}

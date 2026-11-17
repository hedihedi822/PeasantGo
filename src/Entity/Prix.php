<?php

namespace App\Entity;

use App\Repository\PrixRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrixRepository::class)]
class Prix
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $idP;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $idS;

    #[ORM\Column(type: 'float')]
    private float $prixMoyenneExportation;

    #[ORM\Column(type: 'float')]
    private float $prixMoyenneDeGrosParEtat;

    #[ORM\Column(type: 'float')]
    private float $prixMoyenneDeGrosParRegion;

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

    public function getPrixMoyenneExportation(): float
    {
        return $this->prixMoyenneExportation;
    }

    public function setPrixMoyenneExportation(float $prix): self
    {
        $this->prixMoyenneExportation = $prix;
        return $this;
    }

    public function getPrixMoyenneDeGrosParEtat(): float
    {
        return $this->prixMoyenneDeGrosParEtat;
    }

    public function setPrixMoyenneDeGrosParEtat(float $prix): self
    {
        $this->prixMoyenneDeGrosParEtat = $prix;
        return $this;
    }

    public function getPrixMoyenneDeGrosParRegion(): float
    {
        return $this->prixMoyenneDeGrosParRegion;
    }

    public function setPrixMoyenneDeGrosParRegion(float $prix): self
    {
        $this->prixMoyenneDeGrosParRegion = $prix;
        return $this;
    }
}

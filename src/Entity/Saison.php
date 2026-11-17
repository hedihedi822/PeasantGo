<?php

namespace App\Entity;

use App\Repository\SaisonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SaisonRepository::class)]
class Saison
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $idS;

    #[ORM\Column(type: 'integer')]
    private int $idP;

    #[ORM\Column(type: 'integer')]
    private int $annee;

    #[ORM\Column(type: 'integer')]
    private int $numeroSaisonParAn;

    public function getIdS(): int
    {
        return $this->idS;
    }

    public function setIdS(int $idS): self
    {
        $this->idS = $idS;
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

    public function getAnnee(): int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): self
    {
        $this->annee = $annee;
        return $this;
    }

    public function getNumeroSaisonParAn(): int
    {
        return $this->numeroSaisonParAn;
    }

    public function setNumeroSaisonParAn(int $numero): self
    {
        $this->numeroSaisonParAn = $numero;
        return $this;
    }
}


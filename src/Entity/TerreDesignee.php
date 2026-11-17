<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\TerreDesigneeRepository::class)]
#[ORM\Table(name: 'terre_designee')]
class TerreDesignee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer')]
    private $idP;

    #[ORM\Column(type: 'integer')]
    private $idS;

    #[ORM\Column(type: 'float')]
    private $pourcentageTerreDesignee;

    #[ORM\Column(type: 'float')]
    private $pourcentageTerreDesigneeDansEtat;

    #[ORM\Column(type: 'float')]
    private $pourcentageTerreDesigneeDansRegion;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdP(): ?int
    {
        return $this->idP;
    }

    public function setIdP(int $idP): self
    {
        $this->idP = $idP;

        return $this;
    }

    public function getIdS(): ?int
    {
        return $this->idS;
    }

    public function setIdS(int $idS): self
    {
        $this->idS = $idS;

        return $this;
    }

    public function getPourcentageTerreDesignee(): ?float
    {
        return $this->pourcentageTerreDesignee;
    }

    public function setPourcentageTerreDesignee(float $pourcentageTerreDesignee): self
    {
        $this->pourcentageTerreDesignee = $pourcentageTerreDesignee;

        return $this;
    }

    public function getPourcentageTerreDesigneeDansEtat(): ?float
    {
        return $this->pourcentageTerreDesigneeDansEtat;
    }

    public function setPourcentageTerreDesigneeDansEtat(float $pourcentageTerreDesigneeDansEtat): self
    {
        $this->pourcentageTerreDesigneeDansEtat = $pourcentageTerreDesigneeDansEtat;

        return $this;
    }

    public function getPourcentageTerreDesigneeDansRegion(): ?float
    {
        return $this->pourcentageTerreDesigneeDansRegion;
    }

    public function setPourcentageTerreDesigneeDansRegion(float $pourcentageTerreDesigneeDansRegion): self
    {
        $this->pourcentageTerreDesigneeDansRegion = $pourcentageTerreDesigneeDansRegion;

        return $this;
    }
}


<?php
// src/Entity/Outils.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Repository\OutilsRepository")]
class Outils
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_o", type: "integer")]
    private $idO;

    #[ORM\Column(name: "nom_outils", type: "string", length: 255)]
    private $nomOutils;

    #[ORM\Column(name: "importance_outils", type: "string", length: 255)]
    private $importanceOutils;

    #[ORM\Column(name: "prix_outils", type: "float")]
    private $prixOutils;

    // Getters et Setters
    public function getIdO(): ?int
    {
        return $this->idO;
    }

    public function getNomOutils(): ?string
    {
        return $this->nomOutils;
    }

    public function setNomOutils(string $nomOutils): self
    {
        $this->nomOutils = $nomOutils;
        return $this;
    }

    public function getImportanceOutils(): ?string
    {
        return $this->importanceOutils;
    }

    public function setImportanceOutils(string $importanceOutils): self
    {
        $this->importanceOutils = $importanceOutils;
        return $this;
    }

    public function getPrixOutils(): ?float
    {
        return $this->prixOutils;
    }

    public function setPrixOutils(float $prixOutils): self
    {
        $this->prixOutils = $prixOutils;
        return $this;
    }
}

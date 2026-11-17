<?php

namespace App\Entity;

use App\Repository\CodeCompteursRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Compteur (eau / électricité...) installé sur un terrain
 * (fusionne les anciennes classes CodeCompteurs et CompteurUtil).
 */
#[ORM\Entity(repositoryClass: CodeCompteursRepository::class)]
#[ORM\Table(name: 'code_compteurs')]
class CodeCompteurs
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'code_compteur', type: 'integer')]
    private ?int $codeCompteur = null;

    #[ORM\ManyToOne(targetEntity: CodeTerrain::class, inversedBy: 'compteurs')]
    #[ORM\JoinColumn(name: 'code_terrain', referencedColumnName: 'code_terrain', nullable: false, onDelete: 'CASCADE')]
    private ?CodeTerrain $terrain = null;

    #[ORM\Column(type: 'string', length: 60)]
    private string $numero = '';

    #[ORM\Column(type: 'string', length: 60)]
    private string $type = '';

    #[ORM\Column(name: 'en_fonctionnement', type: 'boolean', options: ['default' => true])]
    private bool $enFonctionnement = true;

    public function getCodeCompteur(): ?int
    {
        return $this->codeCompteur;
    }

    public function getId(): ?int
    {
        return $this->codeCompteur;
    }

    public function getTerrain(): ?CodeTerrain
    {
        return $this->terrain;
    }

    public function setTerrain(CodeTerrain $terrain): self
    {
        $this->terrain = $terrain;
        return $this;
    }

    public function getNumero(): string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): self
    {
        $this->numero = $numero;
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

    public function isEnFonctionnement(): bool
    {
        return $this->enFonctionnement;
    }

    public function setEnFonctionnement(bool $enFonctionnement): self
    {
        $this->enFonctionnement = $enFonctionnement;
        return $this;
    }
}

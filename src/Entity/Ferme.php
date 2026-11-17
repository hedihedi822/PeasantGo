<?php

namespace App\Entity;

use App\Repository\FermeRepository;
use Doctrine\ORM\Mapping as ORM;

/** Ferme (bâtiment / exploitation) située sur un terrain. */
#[ORM\Entity(repositoryClass: FermeRepository::class)]
#[ORM\Table(name: 'ferme')]
class Ferme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CodeTerrain::class, inversedBy: 'fermes')]
    #[ORM\JoinColumn(name: 'code_terrain', referencedColumnName: 'code_terrain', nullable: false, onDelete: 'CASCADE')]
    private ?CodeTerrain $terrain = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $localisation = '';

    #[ORM\Column(type: 'string', length: 120)]
    private string $type = '';

    public function getId(): ?int { return $this->id; }
    public function getTerrain(): ?CodeTerrain { return $this->terrain; }
    public function setTerrain(CodeTerrain $t): self { $this->terrain = $t; return $this; }
    public function getLocalisation(): string { return $this->localisation; }
    public function setLocalisation(string $l): self { $this->localisation = $l; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
}

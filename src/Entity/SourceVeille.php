<?php

namespace App\Entity;

use App\Repository\SourceVeilleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Site suivi par le robot (veille) : sites officiels (ministère, offices, INS),
 * marchés de gros, vendeurs d'intrants agricoles d'une zone.
 */
#[ORM\Entity(repositoryClass: SourceVeilleRepository::class)]
#[ORM\Table(name: 'source_veille')]
class SourceVeille
{
    public const TYPES = ['officiel' => 'Site officiel', 'marche' => 'Marché / prix', 'intrants' => 'Vendeur d\'intrants'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 160)]
    private string $nom = '';

    #[ORM\Column(type: 'string', length: 500)]
    private string $url = '';

    #[ORM\Column(type: 'string', length: 20)]
    private string $type = 'officiel';

    /** Zone couverte par la source (prix des intrants d'un vendeur local...) */
    #[ORM\ManyToOne(targetEntity: Zone::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Zone $zone = null;

    /** Mots qui signalent les pages utiles à suivre depuis la page d'accueil du site */
    #[ORM\Column(name: 'mots_cles', type: 'string', length: 255, nullable: true)]
    private ?string $motsCles = 'prix,statistique,production,bulletin,marché,récolte';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(name: 'derniere_visite', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $derniereVisite = null;

    #[ORM\Column(name: 'dernier_resultat', type: 'string', length: 255, nullable: true)]
    private ?string $dernierResultat = null;

    public function getId(): ?int { return $this->id; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): self { $this->nom = $v; return $this; }
    public function getUrl(): string { return $this->url; }
    public function setUrl(string $v): self { $this->url = $v; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $v): self { $this->type = $v; return $this; }
    public function getZone(): ?Zone { return $this->zone; }
    public function setZone(?Zone $z): self { $this->zone = $z; return $this; }
    public function getMotsCles(): array { return array_values(array_filter(array_map('trim', explode(',', (string) $this->motsCles)))); }
    public function setMotsCles(?string $v): self { $this->motsCles = $v; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): self { $this->active = $v; return $this; }
    public function getDerniereVisite(): ?\DateTimeInterface { return $this->derniereVisite; }
    public function setDerniereVisite(?\DateTimeInterface $v): self { $this->derniereVisite = $v; return $this; }
    public function getDernierResultat(): ?string { return $this->dernierResultat; }
    public function setDernierResultat(?string $v): self { $this->dernierResultat = $v ? mb_substr($v, 0, 255) : null; return $this; }
}

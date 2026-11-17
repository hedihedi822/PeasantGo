<?php

namespace App\Entity;

use App\Repository\PrixMarcheRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Prix observé d'un produit agricole à une date et un lieu.
 * Sources : veille (sites officiels / marchés), récoltes déclarées par les agriculteurs, admin.
 */
#[ORM\Entity(repositoryClass: PrixMarcheRepository::class)]
#[ORM\Table(name: 'prix_marche')]
#[ORM\Index(columns: ['culture_cle', 'date_prix'], name: 'idx_prix_culture_date')]
class PrixMarche
{
    public const TYPES = ['gros' => 'Gros', 'detail' => 'Détail', 'producteur' => 'Producteur'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'culture_cle', type: 'string', length: 60)]
    private string $cultureCle = '';

    #[ORM\ManyToOne(targetEntity: Zone::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Zone $zone = null;

    #[ORM\Column(name: 'lieu_texte', type: 'string', length: 160, nullable: true)]
    private ?string $lieuTexte = null;

    #[ORM\Column(name: 'date_prix', type: 'date')]
    private \DateTimeInterface $date;

    #[ORM\Column(name: 'prix_kg', type: 'float')]
    private float $prixKg = 0;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type = 'gros';

    /** veille | recolte | admin | demo */
    #[ORM\Column(type: 'string', length: 20)]
    private string $source = 'admin';

    #[ORM\Column(name: 'source_url', type: 'string', length: 500, nullable: true)]
    private ?string $sourceUrl = null;

    public function __construct(string $cultureCle, \DateTimeInterface $date, float $prixKg, string $source)
    {
        $this->cultureCle = $cultureCle;
        $this->date = $date;
        $this->prixKg = $prixKg;
        $this->source = $source;
    }

    public function getId(): ?int { return $this->id; }
    public function getCultureCle(): string { return $this->cultureCle; }
    public function getZone(): ?Zone { return $this->zone; }
    public function setZone(?Zone $z): self { $this->zone = $z; return $this; }
    public function getLieuTexte(): ?string { return $this->lieuTexte; }
    public function setLieuTexte(?string $v): self { $this->lieuTexte = $v ? mb_substr($v, 0, 160) : null; return $this; }
    public function getDate(): \DateTimeInterface { return $this->date; }
    public function getPrixKg(): float { return $this->prixKg; }
    public function getType(): string { return $this->type; }
    public function setType(string $t): self { $this->type = isset(self::TYPES[$t]) ? $t : 'gros'; return $this; }
    public function getSource(): string { return $this->source; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $v): self { $this->sourceUrl = $v ? mb_substr($v, 0, 500) : null; return $this; }
}

<?php

namespace App\Entity;

use App\Repository\ZoneStatRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Chiffres d'une culture dans une zone pour une année.
 * Plusieurs sources peuvent coexister : officiel (veille), admin, app (déclarations PeasantGo), modele, demo.
 * Le robot choisit la plus fiable (voir BilanZones).
 */
#[ORM\Entity(repositoryClass: ZoneStatRepository::class)]
#[ORM\Table(name: 'zone_stat')]
#[ORM\UniqueConstraint(name: 'uniq_zonestat', columns: ['zone_id', 'culture_cle', 'annee', 'source'])]
class ZoneStat
{
    public const SOURCES = ['officiel' => 'Officielle', 'admin' => 'Équipe PeasantGo', 'app' => 'Déclarations PeasantGo', 'modele' => 'Modèle du robot', 'demo' => 'Démonstration'];
    /** Ordre de confiance (le plus fiable d'abord) */
    public const PRIORITE = ['officiel' => 5, 'admin' => 4, 'app' => 3, 'modele' => 2, 'demo' => 1];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Zone::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Zone $zone = null;

    #[ORM\Column(name: 'culture_cle', type: 'string', length: 60)]
    private string $cultureCle = '';

    #[ORM\Column(type: 'smallint')]
    private int $annee = 2026;

    #[ORM\Column(type: 'string', length: 20)]
    private string $source = 'demo';

    #[ORM\Column(name: 'production_t', type: 'float', nullable: true)]
    private ?float $productionT = null;

    #[ORM\Column(name: 'surface_ha', type: 'float', nullable: true)]
    private ?float $surfaceHa = null;

    /** Consommation par habitant (kg/an) */
    #[ORM\Column(name: 'consommation_kg_hab', type: 'float', nullable: true)]
    private ?float $consommationKgHab = null;

    /** Coût moyen de culture (DT/ha) */
    #[ORM\Column(name: 'cout_ha', type: 'float', nullable: true)]
    private ?float $coutHa = null;

    /** Prix moyen (DT/kg) */
    #[ORM\Column(name: 'prix_kg', type: 'float', nullable: true)]
    private ?float $prixKg = null;

    #[ORM\Column(name: 'source_url', type: 'string', length: 500, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct(Zone $zone, string $cultureCle, int $annee, string $source)
    {
        $this->zone = $zone;
        $this->cultureCle = $cultureCle;
        $this->annee = $annee;
        $this->source = $source;
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getZone(): ?Zone { return $this->zone; }
    public function getCultureCle(): string { return $this->cultureCle; }
    public function getAnnee(): int { return $this->annee; }
    public function getSource(): string { return $this->source; }
    public function getProductionT(): ?float { return $this->productionT; }
    public function setProductionT(?float $v): self { $this->productionT = $v; $this->touch(); return $this; }
    public function getSurfaceHa(): ?float { return $this->surfaceHa; }
    public function setSurfaceHa(?float $v): self { $this->surfaceHa = $v; $this->touch(); return $this; }
    public function getConsommationKgHab(): ?float { return $this->consommationKgHab; }
    public function setConsommationKgHab(?float $v): self { $this->consommationKgHab = $v; $this->touch(); return $this; }
    public function getCoutHa(): ?float { return $this->coutHa; }
    public function setCoutHa(?float $v): self { $this->coutHa = $v; $this->touch(); return $this; }
    public function getPrixKg(): ?float { return $this->prixKg; }
    public function setPrixKg(?float $v): self { $this->prixKg = $v; $this->touch(); return $this; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $v): self { $this->sourceUrl = $v ? mb_substr($v, 0, 500) : null; return $this; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    private function touch(): void { $this->updatedAt = new \DateTime(); }
}

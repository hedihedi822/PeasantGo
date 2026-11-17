<?php

namespace App\Entity;

use App\Repository\ZoneRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Zone géographique utilisée par le robot : monde, pays, gouvernorat ou localité.
 * Les localités sont créées automatiquement à partir des terrains (géocodage Open-Meteo).
 */
#[ORM\Entity(repositoryClass: ZoneRepository::class)]
#[ORM\Table(name: 'zone')]
#[ORM\UniqueConstraint(name: 'uniq_zone_niveau_code', columns: ['niveau', 'code'])]
class Zone
{
    public const NIVEAUX = ['monde' => 'Monde', 'pays' => 'Pays', 'gouvernorat' => 'Gouvernorat', 'localite' => 'Localité'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $niveau = 'localite';

    /** Code unique dans le niveau : "WORLD", "TN", "TN-BEJA", "TN-BEJA-TESTOUR"... */
    #[ORM\Column(type: 'string', length: 80)]
    private string $code = '';

    #[ORM\Column(type: 'string', length: 120)]
    private string $nom = '';

    #[ORM\ManyToOne(targetEntity: Zone::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Zone $parent = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $population = null;

    #[ORM\Column(name: 'annee_population', type: 'smallint', nullable: true)]
    private ?int $anneePopulation = null;

    #[ORM\Column(name: 'source_population', type: 'string', length: 120, nullable: true)]
    private ?string $sourcePopulation = null;

    public function getId(): ?int { return $this->id; }
    public function getNiveau(): string { return $this->niveau; }
    public function setNiveau(string $v): self { $this->niveau = $v; return $this; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $v): self { $this->code = $v; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): self { $this->nom = $v; return $this; }
    public function getParent(): ?Zone { return $this->parent; }
    public function setParent(?Zone $v): self { $this->parent = $v; return $this; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $v): self { $this->latitude = $v; return $this; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $v): self { $this->longitude = $v; return $this; }
    public function getPopulation(): ?int { return $this->population === null ? null : (int) $this->population; }
    public function setPopulation(?int $v): self { $this->population = $v === null ? null : (string) $v; return $this; }
    public function getAnneePopulation(): ?int { return $this->anneePopulation; }
    public function setAnneePopulation(?int $v): self { $this->anneePopulation = $v; return $this; }
    public function getSourcePopulation(): ?string { return $this->sourcePopulation; }
    public function setSourcePopulation(?string $v): self { $this->sourcePopulation = $v; return $this; }

    /** Distance à vol d'oiseau en km. */
    public function distanceKm(Zone $o): ?float
    {
        if ($this->latitude === null || $o->latitude === null) {
            return null;
        }
        return self::haversine($this->latitude, $this->longitude, $o->latitude, $o->longitude);
    }

    public static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $r * asin(min(1, sqrt($a)));
    }
}

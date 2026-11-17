<?php

namespace App\Entity;

use App\Repository\ObservationSatelliteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une image satellite d'un terrain à une date, avec les indices de végétation calculés.
 *  - NDVI : vigueur de la végétation (sol nu ≈ 0,1 ; culture dense ≈ 0,8)
 *  - EVI  : comme NDVI, moins saturé sur les cultures denses
 *  - NDMI : humidité de la végétation
 */
#[ORM\Entity(repositoryClass: ObservationSatelliteRepository::class)]
#[ORM\Table(name: 'observation_satellite')]
#[ORM\UniqueConstraint(name: 'uniq_obs_terrain_source_date', columns: ['code_terrain', 'source', 'date_observation'])]
class ObservationSatellite
{
    public const SOURCES = ['agromonitoring' => 'Agromonitoring', 'cdse' => 'Copernicus (CDSE)', 'earthsearch' => 'Earth Search (STAC)', 'demo' => 'Démo'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CodeTerrain::class)]
    #[ORM\JoinColumn(name: 'code_terrain', referencedColumnName: 'code_terrain', nullable: false, onDelete: 'CASCADE')]
    private ?CodeTerrain $terrain = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $source = '';

    #[ORM\Column(name: 'date_observation', type: 'date')]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $ndvi = null;

    #[ORM\Column(name: 'ndvi_min', type: 'float', nullable: true)]
    private ?float $ndviMin = null;

    #[ORM\Column(name: 'ndvi_max', type: 'float', nullable: true)]
    private ?float $ndviMax = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $evi = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $ndmi = null;

    /** Couverture nuageuse en % */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $nuages = null;

    #[ORM\Column(name: 'image_url', type: 'string', length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(name: 'ndvi_image_url', type: 'string', length: 500, nullable: true)]
    private ?string $ndviImageUrl = null;

    public function __construct(CodeTerrain $terrain, string $source, \DateTimeInterface $date)
    {
        $this->terrain = $terrain;
        $this->source = $source;
        $this->date = $date;
    }

    public function remplir(array $d): self
    {
        foreach (['ndvi', 'ndviMin', 'ndviMax', 'evi', 'ndmi', 'nuages'] as $k) {
            if (array_key_exists($k, $d) && $d[$k] !== null && is_finite((float) $d[$k])) {
                $this->$k = round((float) $d[$k], 4);
            }
        }
        foreach (['imageUrl', 'ndviImageUrl'] as $k) {
            if (!empty($d[$k])) {
                $this->$k = mb_substr((string) $d[$k], 0, 500);
            }
        }
        return $this;
    }

    public function getId(): ?int { return $this->id; }
    public function getTerrain(): ?CodeTerrain { return $this->terrain; }
    public function getSource(): string { return $this->source; }
    public function getDate(): \DateTimeInterface { return $this->date; }
    public function getNdvi(): ?float { return $this->ndvi; }
    public function getNdviMin(): ?float { return $this->ndviMin; }
    public function getNdviMax(): ?float { return $this->ndviMax; }
    public function getEvi(): ?float { return $this->evi; }
    public function getNdmi(): ?float { return $this->ndmi; }
    public function getNuages(): ?float { return $this->nuages; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function getNdviImageUrl(): ?string { return $this->ndviImageUrl; }

    public function toArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'), 'source' => $this->source,
            'ndvi' => $this->ndvi, 'ndviMin' => $this->ndviMin, 'ndviMax' => $this->ndviMax,
            'evi' => $this->evi, 'ndmi' => $this->ndmi, 'nuages' => $this->nuages,
            'imageUrl' => $this->imageUrl, 'ndviImageUrl' => $this->ndviImageUrl,
        ];
    }
}

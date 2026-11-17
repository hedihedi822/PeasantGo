<?php

namespace App\Entity;

use App\Repository\PredictionCultureRepository;
use Doctrine\ORM\Mapping as ORM;

/** Résultat du modèle de reconnaissance des cultures pour un terrain à une date. */
#[ORM\Entity(repositoryClass: PredictionCultureRepository::class)]
#[ORM\Table(name: 'prediction_culture')]
#[ORM\Index(columns: ['code_terrain', 'created_at'], name: 'idx_prediction_terrain_date')]
class PredictionCulture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CodeTerrain::class)]
    #[ORM\JoinColumn(name: 'code_terrain', referencedColumnName: 'code_terrain', nullable: false, onDelete: 'CASCADE')]
    private ?CodeTerrain $terrain = null;

    #[ORM\Column(name: 'culture_cle', type: 'string', length: 60)]
    private string $cultureCle = '';

    #[ORM\Column(name: 'culture_nom', type: 'string', length: 100)]
    private string $cultureNom = '';

    /** Probabilité de la culture prédite × fiabilité des données (0 à 1). */
    #[ORM\Column(type: 'float')]
    private float $confiance = 0;

    /** cle => probabilité, triées par ordre décroissant */
    #[ORM\Column(type: 'json')]
    private array $probabilites = [];

    #[ORM\Column(name: 'nb_observations', type: 'smallint')]
    private int $nbObservations = 0;

    /** Première et dernière date d'image utilisées */
    #[ORM\Column(name: 'periode_debut', type: 'date', nullable: true)]
    private ?\DateTimeInterface $periodeDebut = null;

    #[ORM\Column(name: 'periode_fin', type: 'date', nullable: true)]
    private ?\DateTimeInterface $periodeFin = null;

    #[ORM\Column(name: 'culture_declaree', type: 'string', length: 60, nullable: true)]
    private ?string $cultureDeclaree = null;

    /** null = rien de déclaré ; true/false = la prédiction confirme ou non la déclaration */
    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $conforme = null;

    /** Nombre d'exemples réels (terrains déclarés) utilisés par le modèle */
    #[ORM\Column(name: 'nb_exemples', type: 'integer', options: ['default' => 0])]
    private int $nbExemples = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct(CodeTerrain $terrain)
    {
        $this->terrain = $terrain;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getTerrain(): ?CodeTerrain { return $this->terrain; }
    public function getCultureCle(): string { return $this->cultureCle; }
    public function setCultureCle(string $v): self { $this->cultureCle = $v; return $this; }
    public function getCultureNom(): string { return $this->cultureNom; }
    public function setCultureNom(string $v): self { $this->cultureNom = $v; return $this; }
    public function getConfiance(): float { return $this->confiance; }
    public function setConfiance(float $v): self { $this->confiance = round($v, 3); return $this; }
    public function getProbabilites(): array { return $this->probabilites; }
    public function setProbabilites(array $v): self { $this->probabilites = $v; return $this; }
    public function getNbObservations(): int { return $this->nbObservations; }
    public function setNbObservations(int $v): self { $this->nbObservations = $v; return $this; }
    public function getPeriodeDebut(): ?\DateTimeInterface { return $this->periodeDebut; }
    public function setPeriodeDebut(?\DateTimeInterface $v): self { $this->periodeDebut = $v; return $this; }
    public function getPeriodeFin(): ?\DateTimeInterface { return $this->periodeFin; }
    public function setPeriodeFin(?\DateTimeInterface $v): self { $this->periodeFin = $v; return $this; }
    public function getCultureDeclaree(): ?string { return $this->cultureDeclaree; }
    public function setCultureDeclaree(?string $v): self { $this->cultureDeclaree = $v; return $this; }
    public function isConforme(): ?bool { return $this->conforme; }
    public function setConforme(?bool $v): self { $this->conforme = $v; return $this; }
    public function getNbExemples(): int { return $this->nbExemples; }
    public function setNbExemples(int $v): self { $this->nbExemples = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'culture' => $this->cultureCle, 'nom' => $this->cultureNom, 'confiance' => $this->confiance,
            'probabilites' => $this->probabilites, 'nbObservations' => $this->nbObservations,
            'periode' => [$this->periodeDebut?->format('Y-m-d'), $this->periodeFin?->format('Y-m-d')],
            'declaree' => $this->cultureDeclaree, 'conforme' => $this->conforme, 'nbExemples' => $this->nbExemples,
            'date' => $this->createdAt->format('Y-m-d H:i'),
        ];
    }
}

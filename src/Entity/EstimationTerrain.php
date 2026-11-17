<?php

namespace App\Entity;

use App\Repository\EstimationTerrainRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Résultat du robot pour UNE culture sur UN terrain (une ligne du tableau d'accueil).
 * Recalculé chaque nuit et à la demande ; on garde l'historique (lot = date du calcul).
 */
#[ORM\Entity(repositoryClass: EstimationTerrainRepository::class)]
#[ORM\Table(name: 'estimation_terrain')]
#[ORM\Index(columns: ['code_terrain', 'lot'], name: 'idx_estimation_terrain_lot')]
class EstimationTerrain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CodeTerrain::class)]
    #[ORM\JoinColumn(name: 'code_terrain', referencedColumnName: 'code_terrain', nullable: false, onDelete: 'CASCADE')]
    private ?CodeTerrain $terrain = null;

    /** Identifiant du calcul (horodatage) : toutes les lignes d'un même passage ont le même lot */
    #[ORM\Column(type: 'string', length: 20)]
    private string $lot = '';

    #[ORM\Column(name: 'culture_cle', type: 'string', length: 60)]
    private string $cultureCle = '';

    #[ORM\Column(name: 'culture_nom', type: 'string', length: 100)]
    private string $cultureNom = '';

    #[ORM\Column(name: 'numero_saison', type: 'smallint')]
    private int $numeroSaison = 1;

    #[ORM\Column(name: 'saisons_an', type: 'smallint')]
    private int $saisonsAn = 1;

    #[ORM\Column(name: 'debut_cultivation', type: 'date')]
    private \DateTimeInterface $debutCultivation;

    #[ORM\Column(name: 'fin_cultivation', type: 'date')]
    private \DateTimeInterface $finCultivation;

    #[ORM\Column(name: 'debut_exploitation', type: 'date')]
    private \DateTimeInterface $debutExploitation;

    #[ORM\Column(name: 'fin_exploitation', type: 'date')]
    private \DateTimeInterface $finExploitation;

    /** % */
    #[ORM\Column(name: 'taux_reussite', type: 'float')]
    private float $tauxReussite = 0;

    /** tonnes sur la surface du terrain */
    #[ORM\Column(name: 'production_estimee', type: 'float')]
    private float $productionEstimee = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $exportation = false;

    /** DT sur la surface du terrain */
    #[ORM\Column(type: 'float')]
    private float $cout = 0;

    /** DT/kg dans la localité du terrain */
    #[ORM\Column(type: 'float')]
    private float $prix = 0;

    /** DT : production × prix × réussite − coût */
    #[ORM\Column(type: 'float')]
    private float $benefice = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $recommande = false;

    /** Détail du calcul (météo, eau, prix, sources...) pour l'explication à l'utilisateur */
    #[ORM\Column(type: 'json')]
    private array $details = [];

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct(CodeTerrain $t, string $lot, string $cle, string $nom)
    {
        $this->terrain = $t;
        $this->lot = $lot;
        $this->cultureCle = $cle;
        $this->cultureNom = $nom;
        $this->createdAt = new \DateTime();
        $this->debutCultivation = $this->finCultivation = $this->debutExploitation = $this->finExploitation = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getTerrain(): ?CodeTerrain { return $this->terrain; }
    public function getLot(): string { return $this->lot; }
    public function getCultureCle(): string { return $this->cultureCle; }
    public function getCultureNom(): string { return $this->cultureNom; }
    public function getNumeroSaison(): int { return $this->numeroSaison; }
    public function getSaisonsAn(): int { return $this->saisonsAn; }
    public function getDebutCultivation(): \DateTimeInterface { return $this->debutCultivation; }
    public function getFinCultivation(): \DateTimeInterface { return $this->finCultivation; }
    public function getDebutExploitation(): \DateTimeInterface { return $this->debutExploitation; }
    public function getFinExploitation(): \DateTimeInterface { return $this->finExploitation; }
    public function getTauxReussite(): float { return $this->tauxReussite; }
    public function getProductionEstimee(): float { return $this->productionEstimee; }
    public function isExportation(): bool { return $this->exportation; }
    public function getCout(): float { return $this->cout; }
    public function getPrix(): float { return $this->prix; }
    public function getBenefice(): float { return $this->benefice; }
    public function isRecommande(): bool { return $this->recommande; }
    public function setRecommande(bool $v): self { $this->recommande = $v; return $this; }
    public function getDetails(): array { return $this->details; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function remplir(array $r): self
    {
        $this->numeroSaison = $r['numeroSaison'];
        $this->saisonsAn = $r['saisonsAn'];
        $this->debutCultivation = $r['debutCultivation'];
        $this->finCultivation = $r['finCultivation'];
        $this->debutExploitation = $r['debutExploitation'];
        $this->finExploitation = $r['finExploitation'];
        $this->tauxReussite = round($r['tauxReussite'], 1);
        $this->productionEstimee = round($r['productionEstimee'], 2);
        $this->exportation = $r['exportation'];
        $this->cout = round($r['cout']);
        $this->prix = round($r['prix'], 3);
        $this->benefice = round($r['benefice']);
        $this->details = $r['details'] ?? [];
        return $this;
    }

    public function toArray(): array
    {
        return [
            'cle' => $this->cultureCle, 'nom' => $this->cultureNom,
            'numeroSaison' => $this->numeroSaison, 'saisonsAn' => $this->saisonsAn,
            'cultivation' => [$this->debutCultivation->format('Y-m-d'), $this->finCultivation->format('Y-m-d')],
            'exploitation' => [$this->debutExploitation->format('Y-m-d'), $this->finExploitation->format('Y-m-d')],
            'tauxReussite' => $this->tauxReussite, 'productionEstimee' => $this->productionEstimee,
            'exportation' => $this->exportation, 'cout' => $this->cout, 'prix' => $this->prix,
            'benefice' => $this->benefice, 'recommande' => $this->recommande, 'details' => $this->details,
        ];
    }
}

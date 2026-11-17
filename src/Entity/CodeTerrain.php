<?php

namespace App\Entity;

use App\Repository\CodeTerrainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Terrain appartenant à un utilisateur (remplace l'ancienne table de liaison TerrainUtil).
 */
#[ORM\Entity(repositoryClass: CodeTerrainRepository::class)]
#[ORM\Table(name: 'code_terrain')]
class CodeTerrain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'code_terrain', type: Types::INTEGER)]
    private ?int $codeTerrain = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'terrains')]
    #[ORM\JoinColumn(name: 'proprietaire_id', nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $proprietaire = null;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $nom = '';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $localisation = null;

    #[ORM\Column(name: 'code_postal', type: Types::INTEGER, nullable: true)]
    private ?int $codePostal = null;

    // En hectares
    #[ORM\Column(type: Types::FLOAT)]
    private float $surface = 0;

    #[ORM\Column(name: 'en_exploitation', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $enExploitation = false;

    /** Contour GPS du terrain (géométrie GeoJSON "Polygon", coordonnées [longitude, latitude]). */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $geometrie = null;

    /** Identifiant du polygone chez Agromonitoring (créé automatiquement). */
    #[ORM\Column(name: 'agro_polygon_id', type: Types::STRING, length: 64, nullable: true)]
    private ?string $agroPolygonId = null;

    #[ORM\Column(name: 'satellite_sync_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $satelliteSyncAt = null;

    /** Localité et gouvernorat (trouvés par géocodage de la localisation ou du contour GPS) */
    #[ORM\ManyToOne(targetEntity: Zone::class)]
    #[ORM\JoinColumn(name: 'localite_id', nullable: true, onDelete: 'SET NULL')]
    private ?Zone $localite = null;

    #[ORM\ManyToOne(targetEntity: Zone::class)]
    #[ORM\JoinColumn(name: 'gouvernorat_id', nullable: true, onDelete: 'SET NULL')]
    private ?Zone $gouvernorat = null;

    /** Coordonnées issues du géocodage de la localité (utilisées quand il n'y a pas de contour) */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $longitude = null;

    // ---- Justificatif de propriété ou de location (obligatoire pour chaque terrain) ----

    public const JUSTIFICATIFS = ['propriete' => 'Certificat de propriété', 'location' => 'Contrat de location', 'autre' => 'Autre preuve (héritage, attestation…)'];
    public const STATUTS_JUSTIFICATIF = [
        'a_fournir' => 'Justificatif à fournir',
        'en_attente' => 'En attente de vérification',
        'verifie_ia' => 'Pré-vérifié par le robot',
        'verifie' => 'Vérifié par l\'équipe',
        'refuse' => 'Refusé',
    ];
    /** statuts avec lesquels l'agriculteur peut entrer et ses déclarations comptent dans les statistiques */
    public const STATUTS_UTILISABLES = ['en_attente', 'verifie_ia', 'verifie'];

    #[ORM\Column(name: 'justificatif_type', type: Types::STRING, length: 20, nullable: true)]
    private ?string $justificatifType = null;

    /** nom du fichier dans var/justificatifs (jamais dans public/) */
    #[ORM\Column(name: 'justificatif_fichier', type: Types::STRING, length: 255, nullable: true)]
    private ?string $justificatifFichier = null;

    #[ORM\Column(name: 'justificatif_mime', type: Types::STRING, length: 60, nullable: true)]
    private ?string $justificatifMime = null;

    #[ORM\Column(name: 'justificatif_statut', type: Types::STRING, length: 20, options: ['default' => 'a_fournir'])]
    private string $justificatifStatut = 'a_fournir';

    /** résultat de la vérification (robot + équipe) : nom lu, type lu, surface lue, motifs… */
    #[ORM\Column(name: 'justificatif_verification', type: Types::JSON, nullable: true)]
    private ?array $justificatifVerification = null;

    #[ORM\Column(name: 'justificatif_le', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $justificatifLe = null;

    /** contour généré automatiquement depuis l'adresse (carré de la surface déclarée), à corriger par l'agriculteur */
    #[ORM\Column(name: 'contour_approximatif', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $contourApproximatif = false;

    /** réponses à la fenêtre d'accueil : {campagne: 2026, partie1: "Y-m-d", partie2: "Y-m-d"|"aucune"} */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $questionnaire = null;

    /** @var Collection<int, CodeCompteurs> */
    #[ORM\OneToMany(mappedBy: 'terrain', targetEntity: CodeCompteurs::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $compteurs;

    /** @var Collection<int, Ferme> */
    #[ORM\OneToMany(mappedBy: 'terrain', targetEntity: Ferme::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $fermes;

    /** @var Collection<int, Cultivation> */
    #[ORM\OneToMany(mappedBy: 'terrain', targetEntity: Cultivation::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $cultivations;

    public function __construct()
    {
        $this->compteurs = new ArrayCollection();
        $this->fermes = new ArrayCollection();
        $this->cultivations = new ArrayCollection();
    }

    /** Surface occupée par les cultures actives (hectares). */
    public function getSurfaceUtilisee(): float
    {
        $total = 0.0;
        foreach ($this->cultivations as $c) {
            if ($c->isActif() && $c->getStatut() !== 'termine') {
                $total += $c->getSurface();
            }
        }
        return round($total, 2);
    }

    public function getCodeTerrain(): ?int
    {
        return $this->codeTerrain;
    }

    public function getId(): ?int
    {
        return $this->codeTerrain;
    }

    public function getProprietaire(): ?Utilisateur
    {
        return $this->proprietaire;
    }

    public function setProprietaire(Utilisateur $proprietaire): self
    {
        $this->proprietaire = $proprietaire;
        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(?string $localisation): self
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getCodePostal(): ?int
    {
        return $this->codePostal;
    }

    public function setCodePostal(?int $codePostal): self
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getSurface(): float
    {
        return $this->surface;
    }

    public function setSurface(float $surface): self
    {
        $this->surface = $surface;
        return $this;
    }

    public function isEnExploitation(): bool
    {
        return $this->enExploitation;
    }

    public function setEnExploitation(bool $enExploitation): self
    {
        $this->enExploitation = $enExploitation;
        return $this;
    }

    /** @return Collection<int, CodeCompteurs> */
    public function getCompteurs(): Collection
    {
        return $this->compteurs;
    }

    /** @return Collection<int, Ferme> */
    public function getFermes(): Collection
    {
        return $this->fermes;
    }

    /** @return Collection<int, Cultivation> */
    public function getCultivations(): Collection
    {
        return $this->cultivations;
    }

    public function getGeometrie(): ?array
    {
        return $this->geometrie;
    }

    public function setGeometrie(?array $geometrie): self
    {
        if ($this->geometrie !== $geometrie) {
            $this->agroPolygonId = null; // le polygone distant doit être recréé
        }
        $this->geometrie = $geometrie;
        return $this;
    }

    /** Centre approximatif [lat, lon] du contour. */
    public function getCentre(): ?array
    {
        $ring = $this->geometrie['coordinates'][0] ?? null;
        if (!$ring) {
            return null;
        }
        $lon = array_sum(array_column($ring, 0)) / count($ring);
        $lat = array_sum(array_column($ring, 1)) / count($ring);
        return [$lat, $lon];
    }

    public function getAgroPolygonId(): ?string
    {
        return $this->agroPolygonId;
    }

    public function setAgroPolygonId(?string $id): self
    {
        $this->agroPolygonId = $id;
        return $this;
    }

    public function getSatelliteSyncAt(): ?\DateTimeInterface
    {
        return $this->satelliteSyncAt;
    }

    public function setSatelliteSyncAt(?\DateTimeInterface $d): self
    {
        $this->satelliteSyncAt = $d;
        return $this;
    }

    /** Culture active qui couvre au moins 70 % du terrain (sert d'exemple d'apprentissage). */
    public function getCulturePrincipale(): ?Cultivation
    {
        $best = null;
        foreach ($this->cultivations as $c) {
            if ($c->isActif() && $c->getStatut() !== 'termine' && (!$best || $c->getSurface() > $best->getSurface())) {
                $best = $c;
            }
        }
        return $best && $this->surface > 0 && $best->getSurface() / $this->surface >= 0.7 ? $best : null;
    }

    public function getLocalite(): ?Zone { return $this->localite; }
    public function setLocalite(?Zone $z): self { $this->localite = $z; return $this; }
    public function getGouvernorat(): ?Zone { return $this->gouvernorat; }
    public function setGouvernorat(?Zone $z): self { $this->gouvernorat = $z; return $this; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setCoordonnees(?float $lat, ?float $lon): self { $this->latitude = $lat; $this->longitude = $lon; return $this; }

    /** Point de référence du terrain [lat, lon] : centre du contour GPS, sinon localité géocodée. */
    public function getPoint(): ?array
    {
        return $this->getCentre() ?? ($this->latitude !== null ? [$this->latitude, $this->longitude] : null);
    }

    /** Le terrain est irrigué s'il a un compteur d'eau. */
    public function isIrrigue(): bool
    {
        foreach ($this->compteurs as $c) {
            $t = \Symfony\Component\String\u($c->getType())->ascii()->lower()->toString();
            if (str_contains($t, 'eau') || str_contains($t, 'sonede') || str_contains($t, 'irrig') || str_contains($t, 'puits') || str_contains($t, 'crda')) {
                return true;
            }
        }
        return false;
    }

    public function getJustificatifType(): ?string { return $this->justificatifType; }
    public function getJustificatifFichier(): ?string { return $this->justificatifFichier; }
    public function getJustificatifMime(): ?string { return $this->justificatifMime; }
    public function getJustificatifStatut(): string { return $this->justificatifStatut; }
    public function getJustificatifStatutLabel(): string { return self::STATUTS_JUSTIFICATIF[$this->justificatifStatut] ?? $this->justificatifStatut; }
    public function getJustificatifVerification(): ?array { return $this->justificatifVerification; }
    public function getJustificatifLe(): ?\DateTimeInterface { return $this->justificatifLe; }

    public function setJustificatif(string $type, string $fichier, string $mime): self
    {
        if (!isset(self::JUSTIFICATIFS[$type])) {
            throw new \InvalidArgumentException('Type de justificatif invalide');
        }
        $this->justificatifType = $type;
        $this->justificatifFichier = $fichier;
        $this->justificatifMime = $mime;
        $this->justificatifStatut = 'en_attente';
        $this->justificatifVerification = null;
        $this->justificatifLe = new \DateTime();
        return $this;
    }

    public function setJustificatifStatut(string $statut, ?array $verification = null): self
    {
        if (!isset(self::STATUTS_JUSTIFICATIF[$statut])) {
            throw new \InvalidArgumentException('Statut invalide');
        }
        $this->justificatifStatut = $statut;
        if ($verification !== null) {
            $this->justificatifVerification = array_merge($this->justificatifVerification ?? [], $verification);
        }
        return $this;
    }

    /** L'agriculteur peut entrer avec ce terrain et ses déclarations comptent. */
    public function isUtilisable(): bool
    {
        return in_array($this->justificatifStatut, self::STATUTS_UTILISABLES, true);
    }

    public function isContourApproximatif(): bool { return $this->contourApproximatif; }
    public function setContourApproximatif(bool $v): self { $this->contourApproximatif = $v; return $this; }

    public function getQuestionnaire(): ?array { return $this->questionnaire; }

    /** Partie (1 = cette saison, 2 = saison passée) déjà remplie pour la campagne donnée ? */
    public function questionnaireFait(int $campagne, int $partie): bool
    {
        return ($this->questionnaire['campagne'] ?? null) === $campagne && !empty($this->questionnaire['partie' . $partie]);
    }

    public function marquerQuestionnaire(int $campagne, int $partie, string $valeur): self
    {
        $q = ($this->questionnaire['campagne'] ?? null) === $campagne ? $this->questionnaire : ['campagne' => $campagne];
        $q['partie' . $partie] = $valeur;
        $this->questionnaire = $q;
        return $this;
    }
}

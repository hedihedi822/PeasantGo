<?php

namespace App\Entity;

use App\Repository\CultivationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une culture ou un élevage sur un terrain : prévu cette saison (fenêtre du terrain, partie 1),
 * déclaré pour la saison passée (partie 2), avec la récolte / le résultat une fois terminé.
 * Remplace l'ancien format où toutes les cultures d'un CIN étaient stockées en listes texte.
 */
#[ORM\Entity(repositoryClass: CultivationRepository::class)]
#[ORM\Table(name: 'cultivation')]
#[ORM\Index(columns: ['code_terrain'])]
class Cultivation
{
    public const STATUTS = ['planifie' => 'Planifié', 'en_cours' => 'En cours', 'termine' => 'Terminé'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CodeTerrain::class, inversedBy: 'cultivations')]
    #[ORM\JoinColumn(name: 'code_terrain', referencedColumnName: 'code_terrain', nullable: false, onDelete: 'CASCADE')]
    private ?CodeTerrain $terrain = null;

    #[ORM\ManyToOne(targetEntity: CodeCompteurs::class)]
    #[ORM\JoinColumn(name: 'code_compteur', referencedColumnName: 'code_compteur', nullable: true, onDelete: 'SET NULL')]
    private ?CodeCompteurs $compteur = null;

    // Lien optionnel vers le catalogue ProduitsAgricole (id_p)
    #[ORM\Column(name: 'id_p', type: 'integer', nullable: true)]
    private ?int $idP = null;

    // Clé de la culture (ex: "ble") et nom affiché (ex: "Blé")
    #[ORM\Column(name: 'culture_cle', type: 'string', length: 60)]
    private string $cultureCle = '';

    #[ORM\Column(name: 'culture_nom', type: 'string', length: 100)]
    private string $cultureNom = '';

    #[ORM\Column(type: 'float')]
    private float $surface = 0;

    #[ORM\Column(name: 'date_debut_cultivation', type: 'date')]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(name: 'date_debut_exploitation', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $statut = 'planifie';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    // ---- Récolte déclarée quand la culture est terminée (sert à l'apprentissage du robot) ----

    /** Quantité récoltée en tonnes */
    #[ORM\Column(name: 'quantite_recoltee', type: 'float', nullable: true)]
    private ?float $quantiteRecoltee = null;

    /** Prix de vente moyen obtenu en DT/kg */
    #[ORM\Column(name: 'prix_vente', type: 'float', nullable: true)]
    private ?float $prixVente = null;

    /** Coût réel total dépensé en DT (optionnel) */
    #[ORM\Column(name: 'cout_reel', type: 'float', nullable: true)]
    private ?float $coutReel = null;

    #[ORM\Column(name: 'date_recolte', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateRecolte = null;

    /** reussie | partielle | echec */
    #[ORM\Column(name: 'resultat', type: 'string', length: 20, nullable: true)]
    private ?string $resultat = null;

    #[ORM\Column(name: 'remarque_recolte', type: 'text', nullable: true)]
    private ?string $remarqueRecolte = null;

    // ---- Données de la fenêtre d'accueil (saison passée) et élevage ----

    public const ORIGINES = ['formulaire' => 'Formulaire', 'plan' => 'Prévue cette saison', 'historique' => 'Saison passée'];
    public const CANAUX = [
        'marche_gros' => 'Marché de gros',
        'intermediaire' => 'Intermédiaire (gachar)',
        'direct' => 'Vente directe',
        'cooperative' => 'Coopérative / GDA / office',
        'transformateur' => 'Usine / transformateur',
        'export' => 'Exportateur',
        'autoconsommation' => 'Consommation familiale',
    ];
    public const POSTES_COUTS = [
        'semences' => 'Semences / plants / animaux achetés',
        'engrais' => 'Engrais',
        'traitements' => 'Traitements / vétérinaire',
        'aliment' => 'Aliment du bétail',
        'main_oeuvre' => 'Main-d\'œuvre',
        'eau' => 'Eau / irrigation / énergie',
        'machines' => 'Tracteur / location de machines',
        'transport' => 'Transport / emballage / commission',
        'location' => 'Location de la terre',
        'autres' => 'Autres',
    ];

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'formulaire'])]
    private string $origine = 'formulaire';

    /** élevage : nombre d'animaux / de ruches (la surface est alors 0) */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $effectif = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $irrigue = null;

    /** coûts réels par poste (DT) : clés de POSTES_COUTS */
    #[ORM\Column(name: 'detail_couts', type: 'json', nullable: true)]
    private ?array $detailCouts = null;

    #[ORM\Column(name: 'canal_vente', type: 'string', length: 30, nullable: true)]
    private ?string $canalVente = null;

    public function getOrigine(): string { return $this->origine; }
    public function setOrigine(string $o): self { $this->origine = isset(self::ORIGINES[$o]) ? $o : 'formulaire'; return $this; }
    public function getEffectif(): ?int { return $this->effectif; }
    public function setEffectif(?int $n): self { $this->effectif = $n; return $this; }
    public function getIrrigue(): ?bool { return $this->irrigue; }
    public function setIrrigue(?bool $v): self { $this->irrigue = $v; return $this; }
    public function getDetailCouts(): ?array { return $this->detailCouts; }
    public function setDetailCouts(?array $d): self
    {
        $propre = [];
        foreach ((array) $d as $k => $v) {
            if (isset(self::POSTES_COUTS[$k]) && is_numeric($v) && $v > 0) {
                $propre[$k] = round((float) $v, 2);
            }
        }
        $this->detailCouts = $propre ?: null;
        return $this;
    }
    public function getCanalVente(): ?string { return $this->canalVente; }
    public function setCanalVente(?string $c): self { $this->canalVente = isset(self::CANAUX[(string) $c]) ? $c : null; return $this; }

    /** Taille de la culture : hectares (végétal) ou nombre d'animaux (élevage). */
    public function getTaille(): float
    {
        return $this->effectif !== null && $this->effectif > 0 ? (float) $this->effectif : $this->surface;
    }

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->dateDebut = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCompteur(): ?CodeCompteurs
    {
        return $this->compteur;
    }

    public function setCompteur(?CodeCompteurs $compteur): self
    {
        $this->compteur = $compteur;
        return $this;
    }

    public function getIdP(): ?int
    {
        return $this->idP;
    }

    public function setIdP(?int $idP): self
    {
        $this->idP = $idP;
        return $this;
    }

    public function getCultureCle(): string
    {
        return $this->cultureCle;
    }

    public function setCultureCle(string $cultureCle): self
    {
        $this->cultureCle = $cultureCle;
        return $this;
    }

    public function getCultureNom(): string
    {
        return $this->cultureNom;
    }

    public function setCultureNom(string $cultureNom): self
    {
        $this->cultureNom = $cultureNom;
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

    public function getDateDebut(): \DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): self
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        if (!array_key_exists($statut, self::STATUTS)) {
            throw new \InvalidArgumentException('Statut invalide');
        }
        $this->statut = $statut;
        return $this;
    }

    public function getStatutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public const RESULTATS = ['reussie' => 'Réussie', 'partielle' => 'Partielle', 'echec' => 'Échec'];

    public function getQuantiteRecoltee(): ?float { return $this->quantiteRecoltee; }
    public function getPrixVente(): ?float { return $this->prixVente; }
    public function getCoutReel(): ?float { return $this->coutReel; }
    public function getDateRecolte(): ?\DateTimeInterface { return $this->dateRecolte; }
    public function getResultat(): ?string { return $this->resultat; }
    public function getRemarqueRecolte(): ?string { return $this->remarqueRecolte; }

    public function declarerRecolte(float $quantite, ?float $prixVente, ?float $coutReel, \DateTimeInterface $date, string $resultat, ?string $remarque): self
    {
        if (!isset(self::RESULTATS[$resultat])) {
            throw new \InvalidArgumentException('Résultat invalide');
        }
        $this->quantiteRecoltee = $quantite;
        $this->prixVente = $prixVente;
        $this->coutReel = $coutReel;
        $this->dateRecolte = $date;
        $this->resultat = $resultat;
        $this->remarqueRecolte = $remarque;
        $this->statut = 'termine';
        $this->dateFin ??= $date;
        return $this;
    }

    public function isRecolteDeclaree(): bool
    {
        return $this->quantiteRecoltee !== null;
    }

    /** Rendement obtenu en t/ha (végétal) ou t par animal / ruche (élevage) */
    public function getRendement(): ?float
    {
        $taille = $this->getTaille();
        return $this->quantiteRecoltee !== null && $taille > 0 ? round($this->quantiteRecoltee / $taille, 6) : null;
    }
}

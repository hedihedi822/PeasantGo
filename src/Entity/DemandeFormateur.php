<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Candidature « Devenir formateur » envoyée depuis la page Formations ; l'équipe la traite dans l'admin. */
#[ORM\Entity]
#[ORM\Table(name: 'demande_formateur')]
class DemandeFormateur
{
    public const DOMAINES = [
        'cereales' => 'Céréales et légumineuses',
        'maraichage' => 'Maraîchage et serres',
        'arboriculture' => 'Arboriculture, olivier, palmier',
        'elevage' => 'Élevage',
        'apiculture' => 'Apiculture',
        'irrigation' => 'Irrigation et économie d\'eau',
        'sol' => 'Sol, fertilisation, bio',
        'machines' => 'Machinisme agricole',
        'gestion' => 'Gestion, coûts, vente',
        'autre' => 'Autre',
    ];
    public const FORMATS = ['video' => 'Vidéos', 'atelier' => 'Ateliers sur le terrain', 'document' => 'Fiches / documents', 'direct' => 'Sessions en ligne'];
    public const STATUTS = ['nouvelle' => 'Nouvelle', 'en_cours' => 'En cours', 'acceptee' => 'Acceptée', 'refusee' => 'Refusée'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'string', length: 150)]
    private string $nom = '';

    #[ORM\Column(type: 'string', length: 180)]
    private string $email = '';

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $telephone = null;

    /** Métier / qualification (ingénieur agronome, technicien, agriculteur expérimenté…) */
    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $profession = null;

    #[ORM\Column(type: 'json')]
    private array $domaines = [];

    #[ORM\Column(type: 'json')]
    private array $formats = [];

    #[ORM\Column(name: 'annees_experience', type: 'integer', nullable: true)]
    private ?int $anneesExperience = null;

    #[ORM\Column(name: 'lien_exemple', type: 'string', length: 255, nullable: true)]
    private ?string $lienExemple = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    /** Mot de passe choisi par un candidat qui n'a pas encore de compte (haché ; sert à créer son compte s'il est accepté). */
    #[ORM\Column(name: 'mot_de_passe', type: 'string', length: 255, nullable: true)]
    private ?string $motDePasse = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'nouvelle'])]
    private string $statut = 'nouvelle';

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): self { $this->utilisateur = $u; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $v): self { $this->nom = $v; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $v): self { $this->email = $v; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $v): self { $this->telephone = $v; return $this; }
    public function getProfession(): ?string { return $this->profession; }
    public function setProfession(?string $v): self { $this->profession = $v; return $this; }
    public function getDomaines(): array { return $this->domaines; }
    public function setDomaines(array $v): self { $this->domaines = array_values(array_intersect($v, array_keys(self::DOMAINES))); return $this; }
    public function getFormats(): array { return $this->formats; }
    public function setFormats(array $v): self { $this->formats = array_values(array_intersect($v, array_keys(self::FORMATS))); return $this; }
    public function getAnneesExperience(): ?int { return $this->anneesExperience; }
    public function setAnneesExperience(?int $v): self { $this->anneesExperience = $v; return $this; }
    public function getLienExemple(): ?string { return $this->lienExemple; }
    public function setLienExemple(?string $v): self { $this->lienExemple = $v; return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $v): self { $this->message = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): self { $this->statut = isset(self::STATUTS[$v]) ? $v : $this->statut; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getMotDePasse(): ?string { return $this->motDePasse; }
    public function setMotDePasse(?string $hash): self { $this->motDePasse = $hash; return $this; }
}

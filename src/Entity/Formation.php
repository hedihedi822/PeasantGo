<?php
// src/Entity/Formation.php

namespace App\Entity;

use App\Repository\FormationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[ORM\Table(name: 'formation')]
#[ORM\HasLifecycleCallbacks]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le type est obligatoire')]
    private ?string $type = null;

    #[ORM\Column(name: 'sous_type', type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le sous-type est obligatoire')]
    private ?string $sousType = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire')]
    private ?string $categorie = null;

    #[ORM\Column(name: 'formation_video', type: Types::STRING, length: 255, nullable: true)]
    private ?string $formationVideo = null;

    #[ORM\Column(name: 'formation_pdf', type: Types::STRING, length: 255, nullable: true)]
    private ?string $formationPdf = null;

    #[ORM\Column(name: 'formation_word', type: Types::STRING, length: 255, nullable: true)]
    private ?string $formationWord = null;

    /** Formation payante (le formateur fixe le prix) ou gratuite. */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $payante = false;

    /** Prix en dinars tunisiens (3 décimales = millimes), null si gratuite. */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3, nullable: true)]
    private ?string $prix = null;

    // Ajout des propriétés manquantes pour la compatibilité avec le controller
    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    #[Assert\Length(min: 10, max: 500, minMessage: 'Le titre doit faire au moins 10 caractères')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(min: 50, minMessage: 'La description doit faire au moins 50 caractères')]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Choice(choices: ['Débutant', 'Intermédiaire', 'Avancé'], message: 'Difficulté invalide')]
    private ?string $difficulty = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\PositiveOrZero(message: 'La durée doit être positive')]
    private ?int $duration = null; // En minutes

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $views = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $tags = [];

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    private ?string $averageRating = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $ratingCount = 0;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $posterPath = null;

    /** Formateur qui a publié la formation depuis son espace (null = ajoutée par l'équipe). */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'auteur_id', nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $auteur = null;

    public function getAuteur(): ?Utilisateur { return $this->auteur; }
    public function setAuteur(?Utilisateur $u): self { $this->auteur = $u; return $this; }

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->views = 0;
        $this->active = true;
        $this->ratingCount = 0;
        $this->tags = [];
    }

    // Getters et setters originaux
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getSousType(): ?string
    {
        return $this->sousType;
    }

    public function setSousType(?string $sousType): self
    {
        $this->sousType = $sousType;
        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getFormationVideo(): ?string
    {
        return $this->formationVideo;
    }

    public function setFormationVideo(?string $formationVideo): self
    {
        $this->formationVideo = $formationVideo;
        return $this;
    }

    public function getFormationPdf(): ?string
    {
        return $this->formationPdf;
    }

    public function setFormationPdf(?string $formationPdf): self
    {
        $this->formationPdf = $formationPdf;
        return $this;
    }

    public function getFormationWord(): ?string
    {
        return $this->formationWord;
    }

    public function setFormationWord(?string $formationWord): self
    {
        $this->formationWord = $formationWord;
        return $this;
    }

    // Nouveaux getters et setters pour la compatibilité avec le controller
    public function getTitle(): ?string
    {
        return $this->title ?? $this->type; // Utilise le type comme titre de fallback
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Alias pour getCategorie() - pour compatibilité avec le controller
     */
    public function getCategory(): ?string
    {
        return $this->categorie;
    }

    /**
     * Alias pour setCategorie() - pour compatibilité avec le controller
     */
    public function setCategory(?string $category): self
    {
        $this->categorie = $category;
        return $this;
    }

    public function getDifficulty(): ?string
    {
        return $this->difficulty;
    }

    public function setDifficulty(?string $difficulty): self
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    /**
     * Alias pour getFormationVideo() - pour compatibilité avec le controller
     */
    public function getVideoPath(): ?string
    {
        return $this->formationVideo;
    }

    /**
     * Alias pour setFormationVideo() - pour compatibilité avec le controller
     */
    public function setVideoPath(?string $videoPath): self
    {
        $this->formationVideo = $videoPath;
        return $this;
    }

    public function getPosterPath(): ?string
    {
        return $this->posterPath;
    }

    public function setPosterPath(?string $posterPath): self
    {
        $this->posterPath = $posterPath;
        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): self
    {
        $this->views = $views;
        return $this;
    }

    /**
     * Incrémente le nombre de vues de 1
     */
    public function incrementViews(): self
    {
        $this->views++;
        return $this;
    }

    /**
     * Vérifie si la formation est active
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Définit si la formation est active
     */
    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    /**
     * Alias pour setActive() - pour compatibilité
     */
    public function setIsActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): self
    {
        $this->tags = $tags ?? [];
        return $this;
    }

    public function addTag(string $tag): self
    {
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }
        return $this;
    }

    public function removeTag(string $tag): self
    {
        $key = array_search($tag, $this->tags);
        if ($key !== false) {
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags);
        }
        return $this;
    }

    public function getAverageRating(): ?string
    {
        return $this->averageRating;
    }

    public function setAverageRating(?string $averageRating): self
    {
        $this->averageRating = $averageRating;
        return $this;
    }

    public function getRatingCount(): int
    {
        return $this->ratingCount;
    }

    public function setRatingCount(int $ratingCount): self
    {
        $this->ratingCount = $ratingCount;
        return $this;
    }

    /**
     * Formate la durée en format lisible (ex: "2h 30min")
     */
    public function getFormattedDuration(): string
    {
        if (!$this->duration) {
            return 'N/A';
        }

        $hours = intval($this->duration / 60);
        $minutes = $this->duration % 60;

        if ($hours > 0 && $minutes > 0) {
            return $hours . 'h ' . $minutes . 'min';
        } elseif ($hours > 0) {
            return $hours . 'h';
        } else {
            return $minutes . 'min';
        }
    }

    /**
     * Retourne un extrait de la description
     */
    public function getDescriptionExcerpt(int $length = 150): string
    {
        if (!$this->description) {
            return '';
        }
        
        if (strlen($this->description) <= $length) {
            return $this->description;
        }

        return substr($this->description, 0, $length) . '...';
    }

    /**
     * Retourne le niveau de difficulté sous forme numérique
     */
    public function getDifficultyLevel(): int
    {
        return match($this->difficulty) {
            'Débutant' => 1,
            'Intermédiaire' => 2,
            'Avancé' => 3,
            default => 0
        };
    }

    /**
     * Vérifie si la formation a une vidéo
     */
    public function hasVideo(): bool
    {
        return !empty($this->formationVideo);
    }

    /**
     * Vérifie si la formation a un PDF
     */
    public function hasPdf(): bool
    {
        return !empty($this->formationPdf);
    }

    /**
     * Vérifie si la formation a un document Word
     */
    public function hasWord(): bool
    {
        return !empty($this->formationWord);
    }

    /**
     * Retourne tous les fichiers disponibles pour cette formation
     */
    public function getAvailableFiles(): array
    {
        $files = [];
        
        if ($this->hasVideo()) {
            $files['video'] = $this->formationVideo;
        }
        
        if ($this->hasPdf()) {
            $files['pdf'] = $this->formationPdf;
        }
        
        if ($this->hasWord()) {
            $files['word'] = $this->formationWord;
        }
        
        return $files;
    }

    /**
     * Callback pour mettre à jour la date de modification
     */
    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Méthode pour vérifier si la formation peut être visionnée
     */
    public function canBeViewed(): bool
    {
        return $this->isActive() && ($this->hasVideo() || $this->hasPdf() || $this->hasWord());
    }

    /**
     * Retourne une représentation string de l'objet
     */
    public function __toString(): string
    {
        return $this->getTitle() ?? 'Formation sans titre';
    }

    public function isPayante(): bool
    {
        return $this->payante;
    }

    public function setPayante(bool $payante): self
    {
        $this->payante = $payante;
        if (!$payante) {
            $this->prix = null;
        }
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix !== null ? (float) $this->prix : null;
    }

    public function setPrix(?float $prix): self
    {
        $this->prix = $prix !== null ? number_format($prix, 3, '.', '') : null;
        return $this;
    }

    /** « 45 DT », « 12,500 DT » ou « Gratuite ». */
    public function getPrixAffiche(): string
    {
        if (!$this->payante || $this->prix === null) {
            return 'Gratuite';
        }
        $p = (float) $this->prix;
        return (floor($p) == $p ? number_format($p, 0, ',', ' ') : number_format($p, 3, ',', ' ')) . ' DT';
    }

    /**
     * Le contenu entier (vidéo, documents) est-il ouvert à cet utilisateur ?
     * $statutsAchats : [id formation => statut d'achat] du compte (l'achat suit le compte, pas le terrain).
     */
    public function estAccessiblePour(?Utilisateur $u, array $statutsAchats = []): bool
    {
        if ($u === null) {
            return false;
        }
        if (!$this->payante) {
            return true;
        }
        return ($statutsAchats[$this->id] ?? null) === 'payee'
            || in_array('ROLE_ADMIN', $u->getRoles(), true)
            || ($this->auteur !== null && $this->auteur->getId() === $u->getId());
    }
}

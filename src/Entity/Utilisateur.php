<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Compte de base (héritage SINGLE_TABLE).
 *  - RegularUtilisateur : personne (agriculteur) qui se connecte avec son email
 *  - Societe            : société qui se connecte avec son code société
 *  - Admin              : équipe PeasantGo (accès au panneau /Equipe_PeasantGo)
 */
#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string', length: 20)]
#[ORM\DiscriminatorMap(['admin' => Admin::class, 'societe' => Societe::class, 'regular' => RegularUtilisateur::class])]
#[ORM\HasLifecycleCallbacks]
abstract class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    protected string $email = '';

    // Stocké en texte pour accepter le format international (+216...)
    #[ORM\Column(type: 'string', length: 20)]
    protected string $telephone = '';

    #[ORM\Column(name: 'mot_de_passe', type: 'string')]
    protected string $motDePasse = '';

    // Champs communs aux personnes et aux admins (nuls pour les sociétés)
    #[ORM\Column(type: 'integer', unique: true, nullable: true)]
    protected ?int $cin = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    protected ?string $nom = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    protected ?string $prenom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $adresse = null;

    #[ORM\Column(name: 'date_naissance', type: 'date', nullable: true)]
    protected ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $avatar = null;

    #[ORM\Column(type: 'json')]
    protected array $preferences = [];

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $newsletter = false;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    protected \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'last_login_at', type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $lastLoginAt = null;

    // Réinitialisation du mot de passe (code à 6 chiffres, haché)
    #[ORM\Column(name: 'reset_code_hash', type: 'string', length: 255, nullable: true)]
    protected ?string $resetCodeHash = null;

    #[ORM\Column(name: 'reset_code_expires_at', type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $resetCodeExpiresAt = null;

    /** Formateur accepté par l'équipe : peut publier des formations (vidéos, PDF) depuis son espace. */
    #[ORM\Column(name: 'formateur', type: 'boolean', options: ['default' => false])]
    protected bool $formateur = false;

    #[ORM\Column(name: 'reset_attempts', type: 'smallint', options: ['default' => 0])]
    protected int $resetAttempts = 0;

    /** @var Collection<int, CodeTerrain> */
    #[ORM\OneToMany(mappedBy: 'proprietaire', targetEntity: CodeTerrain::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['codeTerrain' => 'ASC'])]
    protected Collection $terrains;

    /** @var Collection<int, Machine> */
    #[ORM\OneToMany(mappedBy: 'proprietaire', targetEntity: Machine::class, cascade: ['remove'], orphanRemoval: true)]
    protected Collection $machines;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->terrains = new ArrayCollection();
        $this->machines = new ArrayCollection();
    }

    /** Type lisible : "regular", "societe" ou "admin". */
    abstract public function getTypeCompte(): string;

    /** Nom affiché dans l'interface. */
    public function getNomAffiche(): string
    {
        $nom = trim(($this->prenom ?? '') . ' ' . ($this->nom ?? ''));
        return $nom !== '' ? $nom : $this->email;
    }

    public function getInitiales(): string
    {
        $parts = preg_split('/\s+/', $this->getNomAffiche()) ?: [];
        $ini = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $ini ?: 'U';
    }

    // ---- UserInterface ----

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->formateur ? ['ROLE_USER', 'ROLE_FORMATEUR'] : ['ROLE_USER'];
    }

    public function isFormateur(): bool
    {
        return $this->formateur;
    }

    public function setFormateur(bool $v): self
    {
        $this->formateur = $v;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getPassword(): ?string
    {
        return $this->motDePasse;
    }

    // ---- Getters / setters ----

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));
        return $this;
    }

    public function getTelephone(): string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): self
    {
        $this->telephone = preg_replace('/[\s.\-()]/', '', $telephone);
        return $this;
    }

    public function getMotDePasse(): string
    {
        return $this->motDePasse;
    }

    /** Attend un mot de passe DÉJÀ haché (UserPasswordHasherInterface). */
    public function setMotDePasse(string $motDePasse): self
    {
        $this->motDePasse = $motDePasse;
        return $this;
    }

    public function getCin(): ?int
    {
        return $this->cin;
    }

    public function setCin(?int $cin): self
    {
        $this->cin = $cin;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeInterface $dateNaissance): self
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getPreferences(): array
    {
        return $this->preferences + [
            'emailNotifications' => true,
            'pushNotifications' => false,
            'alertesEmail' => false,
            'alertesSms' => false,
            'darkMode' => false,
            'langue' => 'fr',
        ];
    }

    public function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;
        return $this;
    }

    public function isNewsletter(): bool
    {
        return $this->newsletter;
    }

    public function setNewsletter(bool $newsletter): self
    {
        $this->newsletter = $newsletter;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getResetCodeHash(): ?string
    {
        return $this->resetCodeHash;
    }

    public function getResetCodeExpiresAt(): ?\DateTimeInterface
    {
        return $this->resetCodeExpiresAt;
    }

    public function getResetAttempts(): int
    {
        return $this->resetAttempts;
    }

    public function setResetCode(?string $hash, ?\DateTimeInterface $expiresAt): self
    {
        $this->resetCodeHash = $hash;
        $this->resetCodeExpiresAt = $expiresAt;
        $this->resetAttempts = 0;
        return $this;
    }

    public function incrementResetAttempts(): self
    {
        ++$this->resetAttempts;
        return $this;
    }

    /** @return Collection<int, CodeTerrain> */
    public function getTerrains(): Collection
    {
        return $this->terrains;
    }

    /** @return Collection<int, Machine> */
    public function getMachines(): Collection
    {
        return $this->machines;
    }
}

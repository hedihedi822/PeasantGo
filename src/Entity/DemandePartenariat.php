<?php

namespace App\Entity;

use App\Repository\DemandePartenariatRepository;
use Doctrine\ORM\Mapping as ORM;

/** Demande envoyée depuis la page "Rejoignez nos partenaires". */
#[ORM\Entity(repositoryClass: DemandePartenariatRepository::class)]
#[ORM\Table(name: 'demande_partenariat')]
class DemandePartenariat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email = '';

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $facebook = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $instagram = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $whatsapp = null;

    #[ORM\Column(name: 'autre_contact', type: 'string', length: 255, nullable: true)]
    private ?string $autreContact = null;

    #[ORM\Column(name: 'methodes_aide', type: 'json')]
    private array $methodesAide = [];

    #[ORM\Column(name: 'autre_aide', type: 'text', nullable: true)]
    private ?string $autreAide = null;

    #[ORM\Column(name: 'services_gratuits', type: 'json')]
    private array $servicesGratuits = [];

    #[ORM\Column(name: 'a_propos', type: 'text', nullable: true)]
    private ?string $aPropos = null;

    #[ORM\Column(name: 'source', type: 'string', length: 30, nullable: true)]
    private ?string $source = null;

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
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $v): self { $this->email = $v; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $v): self { $this->telephone = $v; return $this; }
    public function getFacebook(): ?string { return $this->facebook; }
    public function setFacebook(?string $v): self { $this->facebook = $v; return $this; }
    public function getInstagram(): ?string { return $this->instagram; }
    public function setInstagram(?string $v): self { $this->instagram = $v; return $this; }
    public function getWhatsapp(): ?string { return $this->whatsapp; }
    public function setWhatsapp(?string $v): self { $this->whatsapp = $v; return $this; }
    public function getAutreContact(): ?string { return $this->autreContact; }
    public function setAutreContact(?string $v): self { $this->autreContact = $v; return $this; }
    public function getMethodesAide(): array { return $this->methodesAide; }
    public function setMethodesAide(array $v): self { $this->methodesAide = array_values($v); return $this; }
    public function getAutreAide(): ?string { return $this->autreAide; }
    public function setAutreAide(?string $v): self { $this->autreAide = $v; return $this; }
    public function getServicesGratuits(): array { return $this->servicesGratuits; }
    public function setServicesGratuits(array $v): self { $this->servicesGratuits = array_values($v); return $this; }
    public function getAPropos(): ?string { return $this->aPropos; }
    public function setAPropos(?string $v): self { $this->aPropos = $v; return $this; }
    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $v): self { $this->source = $v; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): self { $this->statut = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}

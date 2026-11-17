<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

/** Notification affichée dans la cloche de la barre de navigation (une ligne par utilisateur). */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\Index(columns: ['utilisateur_id', 'lue'], name: 'idx_notification_user_lue')]
class Notification
{
    public const TYPES = ['statistiques' => 'Statistiques', 'annonce' => 'Annonce', 'compte' => 'Compte'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type = 'statistiques';

    #[ORM\Column(type: 'string', length: 150)]
    private string $titre = '';

    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $lien = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $lue = false;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(Utilisateur $u): self { $this->utilisateur = $u; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): self { $this->message = $message; return $this; }
    public function getLien(): ?string { return $this->lien; }
    public function setLien(?string $lien): self { $this->lien = $lien; return $this; }
    public function isLue(): bool { return $this->lue; }
    public function setLue(bool $lue): self { $this->lue = $lue; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}

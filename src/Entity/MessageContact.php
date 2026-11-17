<?php

namespace App\Entity;

use App\Repository\MessageContactRepository;
use Doctrine\ORM\Mapping as ORM;

/** Message envoyé à l'administration depuis l'onglet "Contact" du profil. */
#[ORM\Entity(repositoryClass: MessageContactRepository::class)]
#[ORM\Table(name: 'message_contact')]
class MessageContact
{
    public const SUJETS = ['support' => 'Support technique', 'billing' => 'Facturation', 'feature' => 'Demande de fonctionnalité', 'bug' => 'Signaler un bug', 'other' => 'Autre'];
    public const PRIORITES = ['low' => 'Faible', 'medium' => 'Moyenne', 'high' => 'Élevée', 'urgent' => 'Urgente'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $sujet = 'other';

    #[ORM\Column(type: 'string', length: 20)]
    private string $priorite = 'medium';

    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $traite = false;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $u): self { $this->utilisateur = $u; return $this; }
    public function getSujet(): string { return $this->sujet; }
    public function setSujet(string $v): self { $this->sujet = $v; return $this; }
    public function getPriorite(): string { return $this->priorite; }
    public function setPriorite(string $v): self { $this->priorite = $v; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $v): self { $this->message = $v; return $this; }
    public function isTraite(): bool { return $this->traite; }
    public function setTraite(bool $v): self { $this->traite = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}

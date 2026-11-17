<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Alerte importante du robot (changement de meilleure culture, marché qui se sature, gel ou chaleur prévus,
 * baisse de végétation vue par satellite), envoyée par email et/ou SMS selon les paramètres de l'utilisateur.
 * La cloche du site reste réservée aux informations de l'application.
 */
#[ORM\Entity]
#[ORM\Table(name: 'alerte_estimation')]
#[ORM\Index(columns: ['utilisateur_id', 'cle'])]
class AlerteEstimation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
        #[ORM\JoinColumn(name: 'utilisateur_id', nullable: false, onDelete: 'CASCADE')]
        private Utilisateur $utilisateur,
        #[ORM\ManyToOne(targetEntity: CodeTerrain::class)]
        #[ORM\JoinColumn(name: 'code_terrain', referencedColumnName: 'code_terrain', nullable: true, onDelete: 'CASCADE')]
        private ?CodeTerrain $terrain,
        /** clé anti-doublon (type + terrain + culture + période) */
        #[ORM\Column(type: 'string', length: 150)]
        private string $cle,
        #[ORM\Column(type: 'string', length: 150)]
        private string $titre,
        #[ORM\Column(type: 'text')]
        private string $message,
        /** canaux réellement utilisés : email, sms */
        #[ORM\Column(type: 'json')]
        private array $canaux = [],
    ) {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): Utilisateur { return $this->utilisateur; }
    public function getTerrain(): ?CodeTerrain { return $this->terrain; }
    public function getCle(): string { return $this->cle; }
    public function getTitre(): string { return $this->titre; }
    public function getMessage(): string { return $this->message; }
    public function getCanaux(): array { return $this->canaux; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}

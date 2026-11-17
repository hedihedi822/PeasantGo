<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Achat d'une formation payante. Il est lié au COMPTE de l'agriculteur (pas à un terrain) :
 * une fois payée, la formation est visible en entier quel que soit le terrain avec lequel il entre.
 * Statuts : en_attente (paiement déclaré, à vérifier par l'équipe), payee, refusee.
 */
#[ORM\Entity]
#[ORM\Table(name: 'achat_formation')]
#[ORM\UniqueConstraint(name: 'achat_unique', columns: ['utilisateur_id', 'formation_id'])]
class AchatFormation
{
    public const MOYENS = ['d17' => 'D17 (La Poste)', 'flouci' => 'Flouci', 'virement' => 'Virement bancaire', 'mandat' => 'Mandat postal'];
    public const STATUTS = ['en_attente' => 'En attente de vérification', 'payee' => 'Payée', 'refusee' => 'Refusée'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 3)]
    private string $prix;

    #[ORM\Column(type: 'string', length: 20)]
    private string $statut = 'en_attente';

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $moyen = null;

    /** référence de la transaction donnée par l'agriculteur (n° D17, n° de virement…) */
    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'payee_le', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $payeeLe = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
        #[ORM\JoinColumn(name: 'utilisateur_id', nullable: false, onDelete: 'CASCADE')]
        private Utilisateur $utilisateur,
        #[ORM\ManyToOne(targetEntity: Formation::class)]
        #[ORM\JoinColumn(name: 'formation_id', nullable: false, onDelete: 'CASCADE')]
        private Formation $formation,
    ) {
        $this->prix = number_format((float) $formation->getPrix(), 3, '.', '');
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): Utilisateur { return $this->utilisateur; }
    public function getFormation(): Formation { return $this->formation; }
    public function getPrix(): float { return (float) $this->prix; }
    public function getStatut(): string { return $this->statut; }
    public function getMoyen(): ?string { return $this->moyen; }
    public function getReference(): ?string { return $this->reference; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getPayeeLe(): ?\DateTimeInterface { return $this->payeeLe; }
    public function isPayee(): bool { return $this->statut === 'payee'; }

    /** Nouvelle déclaration de paiement (aussi après un refus). */
    public function declarer(?string $moyen, ?string $reference): self
    {
        $this->moyen = $moyen;
        $this->reference = $reference;
        $this->prix = number_format((float) $this->formation->getPrix(), 3, '.', '');
        $this->statut = 'en_attente';
        $this->createdAt = new \DateTimeImmutable();
        return $this;
    }

    public function confirmer(): self
    {
        $this->statut = 'payee';
        $this->payeeLe = new \DateTimeImmutable();
        return $this;
    }

    public function refuser(): self
    {
        $this->statut = 'refusee';
        return $this;
    }
}

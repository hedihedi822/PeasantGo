<?php

namespace App\Entity;

use App\Repository\ModeleCalibrationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ce que le robot a appris des récoltes réelles pour une culture dans une zone :
 * facteur de rendement (réel / estimé), taux de réussite observé, biais de prix.
 */
#[ORM\Entity(repositoryClass: ModeleCalibrationRepository::class)]
#[ORM\Table(name: 'modele_calibration')]
#[ORM\UniqueConstraint(name: 'uniq_calibration', columns: ['culture_cle', 'zone_id'])]
class ModeleCalibration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'culture_cle', type: 'string', length: 60)]
    private string $cultureCle = '';

    /** null = national */
    #[ORM\ManyToOne(targetEntity: Zone::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Zone $zone = null;

    #[ORM\Column(name: 'nb_recoltes', type: 'integer')]
    private int $nbRecoltes = 0;

    #[ORM\Column(name: 'nb_reussites', type: 'integer')]
    private int $nbReussites = 0;

    /** Rendement réel moyen (t/ha) */
    #[ORM\Column(name: 'rendement_moyen', type: 'float', nullable: true)]
    private ?float $rendementMoyen = null;

    /** Moyenne de (rendement réel / rendement estimé) */
    #[ORM\Column(name: 'facteur_rendement', type: 'float')]
    private float $facteurRendement = 1.0;

    /** Moyenne de (prix réel / prix estimé) */
    #[ORM\Column(name: 'facteur_prix', type: 'float')]
    private float $facteurPrix = 1.0;

    #[ORM\Column(name: 'nb_prix', type: 'integer', options: ['default' => 0])]
    private int $nbPrix = 0;

    /** Moyenne de (coût réel / coût estimé) */
    #[ORM\Column(name: 'facteur_cout', type: 'float', options: ['default' => 1])]
    private float $facteurCout = 1.0;

    #[ORM\Column(name: 'nb_couts', type: 'integer', options: ['default' => 0])]
    private int $nbCouts = 0;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct(string $cultureCle, ?Zone $zone)
    {
        $this->cultureCle = $cultureCle;
        $this->zone = $zone;
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getCultureCle(): string { return $this->cultureCle; }
    public function getZone(): ?Zone { return $this->zone; }
    public function getNbRecoltes(): int { return $this->nbRecoltes; }
    public function getNbReussites(): int { return $this->nbReussites; }
    public function getRendementMoyen(): ?float { return $this->rendementMoyen; }
    public function getFacteurRendement(): float { return $this->facteurRendement; }
    public function getFacteurPrix(): float { return $this->facteurPrix; }
    public function getNbPrix(): int { return $this->nbPrix; }
    public function getFacteurCout(): float { return $this->facteurCout; }
    public function getNbCouts(): int { return $this->nbCouts; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    public function mettreAJour(int $nbRecoltes, int $nbReussites, ?float $rendementMoyen, float $facteurRendement, float $facteurPrix, int $nbPrix, float $facteurCout, int $nbCouts): self
    {
        $this->nbRecoltes = $nbRecoltes;
        $this->nbReussites = $nbReussites;
        $this->rendementMoyen = $rendementMoyen;
        $this->facteurRendement = $facteurRendement;
        $this->facteurPrix = $facteurPrix;
        $this->nbPrix = $nbPrix;
        $this->facteurCout = $facteurCout;
        $this->nbCouts = $nbCouts;
        $this->updatedAt = new \DateTime();
        return $this;
    }
}

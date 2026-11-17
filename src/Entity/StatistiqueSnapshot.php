<?php

namespace App\Entity;

use App\Repository\StatistiqueSnapshotRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Photo des statistiques du catalogue à une date donnée.
 * Sert à détecter ce qui a changé depuis la dernière publication (et plus tard à tracer des courbes).
 */
#[ORM\Entity(repositoryClass: StatistiqueSnapshotRepository::class)]
#[ORM\Table(name: 'statistique_snapshot')]
class StatistiqueSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    /** "idP-idS" => [taux, exces, cout, prix, confiance, production, libelle] */
    #[ORM\Column(type: 'json')]
    private array $donnees = [];

    public function __construct(array $donnees = [])
    {
        $this->createdAt = new \DateTime();
        $this->donnees = $donnees;
    }

    public function getId(): ?int { return $this->id; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getDonnees(): array { return $this->donnees; }
}

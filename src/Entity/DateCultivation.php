<?php
// src/Entity/DateCultivation.php

namespace App\Entity;

use App\Repository\DateCultivationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DateCultivationRepository::class)]
#[ORM\Table(name: 'date_cultivation')]
class DateCultivation
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_p', type: Types::INTEGER)]
    private int $idP;

    #[ORM\Id]
    #[ORM\Column(name: 'id_s', type: Types::INTEGER)]
    private int $idS;

    #[ORM\Column(name: 'date_debut_cultivation', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateDebutCultivation;

    #[ORM\Column(name: 'date_debut_cultivation_etat', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateDebutCultivationDansEtat;

    #[ORM\Column(name: 'date_debut_cultivation_region', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateDebutCultivationDansRegion;

    #[ORM\Column(name: 'date_debut_exploitation', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateDebutExploitation;

    #[ORM\Column(name: 'date_debut_exploitation_etat', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateDebutExploitationDansEtat;

    #[ORM\Column(name: 'date_debut_exploitation_region', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateDebutExploitationDansRegion;

    // Getters et setters
    public function getIdP(): int
    {
        return $this->idP;
    }

    public function setIdP(int $idP): self
    {
        $this->idP = $idP;
        return $this;
    }

    public function getIdS(): int
    {
        return $this->idS;
    }

    public function setIdS(int $idS): self
    {
        $this->idS = $idS;
        return $this;
    }

    public function getDateDebutCultivation(): \DateTimeInterface
    {
        return $this->dateDebutCultivation;
    }

    public function setDateDebutCultivation(\DateTimeInterface $dateDebutCultivation): self
    {
        $this->dateDebutCultivation = $dateDebutCultivation;
        return $this;
    }

    public function getDateDebutCultivationDansEtat(): \DateTimeInterface
    {
        return $this->dateDebutCultivationDansEtat;
    }

    public function setDateDebutCultivationDansEtat(\DateTimeInterface $dateDebutCultivationDansEtat): self
    {
        $this->dateDebutCultivationDansEtat = $dateDebutCultivationDansEtat;
        return $this;
    }


    public function getDateDebutCultivationDansRegion(): \DateTimeInterface
    {
        return $this->dateDebutCultivationDansRegion;
    }

    public function setDateDebutCultivationDansRegion(\DateTimeInterface $dateDebutCultivationDansRegion): self
    {
        $this->dateDebutCultivationDansRegion = $dateDebutCultivationDansRegion;
        return $this;
    }

    public function getDateDebutExploitation(): \DateTimeInterface
    {
        return $this->dateDebutExploitation;
    }

    public function setDateDebutExploitation(\DateTimeInterface $dateDebutExploitation): self
    {
        $this->dateDebutExploitation = $dateDebutExploitation;
        return $this;
    }

    public function getDateDebutExploitationDansEtat(): \DateTimeInterface
    {
        return $this->dateDebutExploitationDansEtat;
    }

    public function setDateDebutExploitationDansEtat(\DateTimeInterface $dateDebutExploitationDansEtat): self
    {
        $this->dateDebutExploitationDansEtat = $dateDebutExploitationDansEtat;
        return $this;
    }

    public function getDateDebutExploitationDansRegion(): \DateTimeInterface
    {
        return $this->dateDebutExploitationDansRegion;
    }

    public function setDateDebutExploitationDansRegion(\DateTimeInterface $dateDebutExploitationDansRegion): self
    {
        $this->dateDebutExploitationDansRegion = $dateDebutExploitationDansRegion;
        return $this;
    }
}

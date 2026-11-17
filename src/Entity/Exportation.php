<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\ExportationRepository')]
#[ORM\Table(name: 'exportation')]
class Exportation
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_p', type: 'integer')]
    private int $idP;

    #[ORM\Id]
    #[ORM\Column(name: 'id_s', type: 'integer')]
    private int $idS;

    #[ORM\Column(name: 'export_exist', type: 'boolean')]
    private bool $exportExist;

    #[ORM\Column(name: 'quantite_exporte', type: 'float')]
    private float $quantiteExporte;

    #[ORM\Column(name: 'societe_export', type: 'string', length: 255)]
    private string $societeExport;

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

    public function isExportExist(): bool
    {
        return $this->exportExist;
    }

    public function setExportExist(bool $exportExist): self
    {
        $this->exportExist = $exportExist;
        return $this;
    }

    public function getQuantiteExporte(): float
    {
        return $this->quantiteExporte;
    }

    public function setQuantiteExporte(float $quantiteExporte): self
    {
        $this->quantiteExporte = $quantiteExporte;
        return $this;
    }

    public function getSocieteExport(): string
    {
        return $this->societeExport;
    }

    public function setSocieteExport(string $societeExport): self
    {
        $this->societeExport = $societeExport;
        return $this;
    }
}

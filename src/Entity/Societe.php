<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Société. Se connecte avec son code société + mot de passe.
 * (Colonnes nulles pour les autres types : héritage SINGLE_TABLE.)
 */
#[ORM\Entity]
class Societe extends Utilisateur
{
    #[ORM\Column(name: 'nom_societe', type: 'string', length: 35, nullable: true)]
    private ?string $nomSociete = null;

    #[ORM\Column(name: 'code_fiscal', type: 'string', length: 20, unique: true, nullable: true)]
    private ?string $codeFiscal = null;

    #[ORM\Column(name: 'numero_siret', type: 'string', length: 14, unique: true, nullable: true)]
    private ?string $numeroSiret = null;

    #[ORM\Column(name: 'code_societe', type: 'string', length: 20, unique: true, nullable: true)]
    private ?string $codeSociete = null;

    public function getTypeCompte(): string
    {
        return 'societe';
    }

    public function getRoles(): array
    {
        return $this->isFormateur() ? ['ROLE_USER', 'ROLE_SOCIETE', 'ROLE_FORMATEUR'] : ['ROLE_USER', 'ROLE_SOCIETE'];
    }

    public function getNomAffiche(): string
    {
        return $this->nomSociete ?: parent::getNomAffiche();
    }

    public function getNomSociete(): ?string
    {
        return $this->nomSociete;
    }

    public function setNomSociete(string $nomSociete): self
    {
        $this->nomSociete = $nomSociete;
        return $this;
    }

    public function getCodeFiscal(): ?string
    {
        return $this->codeFiscal;
    }

    public function setCodeFiscal(string $codeFiscal): self
    {
        $this->codeFiscal = strtoupper($codeFiscal);
        return $this;
    }

    public function getNumeroSiret(): ?string
    {
        return $this->numeroSiret;
    }

    public function setNumeroSiret(string $numeroSiret): self
    {
        $this->numeroSiret = $numeroSiret;
        return $this;
    }

    public function getCodeSociete(): ?string
    {
        return $this->codeSociete;
    }

    public function setCodeSociete(string $codeSociete): self
    {
        $this->codeSociete = strtoupper(trim($codeSociete));
        return $this;
    }
}

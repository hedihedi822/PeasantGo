<?php

namespace App\Entity;

use App\Repository\PrixIntrantRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Prix d'un intrant agricole (semence, engrais, traitement, eau, gasoil, location de machine...)
 * relevé sur un site de vente ou saisi par l'équipe, dans une zone.
 */
#[ORM\Entity(repositoryClass: PrixIntrantRepository::class)]
#[ORM\Table(name: 'prix_intrant')]
class PrixIntrant
{
    /** cle => [libellé, unité] — les clés sont utilisées par le référentiel agronomique */
    public const INTRANTS = [
        'semence_cereale' => ['Semence céréales', 'kg'],
        'semence_legumineuse' => ['Semence légumineuses', 'kg'],
        'plant_maraichage' => ['Plants maraîchers', 'unité'],
        'semence_maraichage' => ['Semence maraîchage', 'kg'],
        'plant_pomme_terre' => ['Semence pomme de terre', 'kg'],
        'ammonitrate' => ['Ammonitrate 33,5 %', 'kg'],
        'dap' => ['DAP 18-46', 'kg'],
        'potasse' => ['Sulfate de potasse', 'kg'],
        'traitement' => ['Produits phytosanitaires', 'ha'],
        'eau_irrigation' => ['Eau d\'irrigation', 'm3'],
        'gasoil' => ['Gasoil agricole', 'litre'],
        'machine_heure' => ['Location tracteur', 'heure'],
        'moisson_ha' => ['Moissonneuse', 'ha'],
        'transport_tkm' => ['Transport routier', 't·km'],
        'poussin' => ['Poussin d\'un jour (chair)', 'unité'],
        'poulette' => ['Poulette prête à pondre', 'unité'],
        'aliment_chair' => ['Aliment volaille chair', 'kg'],
        'aliment_ponte' => ['Aliment volaille ponte', 'kg'],
        'aliment_ruminant' => ['Aliment concentré ruminants', 'kg'],
        'foin' => ['Foin / fourrage', 'kg'],
        'agneau_maigre' => ['Agneau maigre (achat)', 'kg vif'],
        'sucre' => ['Sucre (nourrissement abeilles)', 'kg'],
        'veto_dt' => ['Soins vétérinaires', 'DT'],
        'divers_dt' => ['Énergie, litière, divers', 'DT'],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 40)]
    private string $intrant = '';

    #[ORM\ManyToOne(targetEntity: Zone::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Zone $zone = null;

    /** Prix en DT par unité */
    #[ORM\Column(type: 'float')]
    private float $prix = 0;

    #[ORM\Column(name: 'date_prix', type: 'date')]
    private \DateTimeInterface $date;

    /** veille | admin | demo */
    #[ORM\Column(type: 'string', length: 20)]
    private string $source = 'admin';

    #[ORM\Column(name: 'source_url', type: 'string', length: 500, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(type: 'string', length: 160, nullable: true)]
    private ?string $vendeur = null;

    public function __construct(string $intrant, float $prix, \DateTimeInterface $date, string $source)
    {
        $this->intrant = $intrant;
        $this->prix = $prix;
        $this->date = $date;
        $this->source = $source;
    }

    public function getId(): ?int { return $this->id; }
    public function getIntrant(): string { return $this->intrant; }
    public function getZone(): ?Zone { return $this->zone; }
    public function setZone(?Zone $z): self { $this->zone = $z; return $this; }
    public function getPrix(): float { return $this->prix; }
    public function getDate(): \DateTimeInterface { return $this->date; }
    public function getSource(): string { return $this->source; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $v): self { $this->sourceUrl = $v ? mb_substr($v, 0, 500) : null; return $this; }
    public function getVendeur(): ?string { return $this->vendeur; }
    public function setVendeur(?string $v): self { $this->vendeur = $v ? mb_substr($v, 0, 160) : null; return $this; }
}

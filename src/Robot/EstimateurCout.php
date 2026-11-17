<?php

namespace App\Robot;

use App\Entity\PrixIntrant;
use App\Entity\Zone;
use App\Repository\PrixIntrantRepository;

/**
 * Coût de culture par hectare dans une zone :
 *   intrants (quantités du référentiel × derniers prix relevés dans la zone, sinon ailleurs)
 * + main d'œuvre (jours × SMAG de l'année)
 * + tracteur (heures × location) + moisson
 * + eau d'irrigation (déficit de pluie × 10 m³/ha/mm ÷ efficacité 0,8) si le terrain est irrigué.
 */
class EstimateurCout
{
    /** Prix par défaut (DT) quand aucun prix n'a encore été relevé – à remplacer par la veille / l'admin */
    public const PRIX_DEFAUT = [
        'semence_cereale' => 1.5, 'semence_legumineuse' => 3.5, 'semence_maraichage' => 250, 'semence_oleagineux' => 60, 'semence_mais' => 30,
        'plant_maraichage' => 0.12, 'plant_pomme_terre' => 2.2, 'ammonitrate' => 1.1, 'dap' => 1.5, 'potasse' => 2.5,
        'traitement' => 250, 'eau_irrigation' => 0.12, 'gasoil' => 2.0, 'machine_heure' => 45, 'moisson_ha' => 120, 'transport_tkm' => 0.18,
        'poussin' => 1.7, 'poulette' => 12.0, 'aliment_chair' => 1.45, 'aliment_ponte' => 1.25, 'aliment_ruminant' => 1.05, 'foin' => 0.55,
        'agneau_maigre' => 17.0, 'sucre' => 1.4, 'veto_dt' => 1.0, 'divers_dt' => 1.0,
    ];

    /** @var array<string, array{0: float, 1: string}> */
    private array $cachePrix = [];

    public function __construct(private PrixIntrantRepository $prix)
    {
    }

    /**
     * @return array{parHa: float, postes: array<string, float>, sources: array<string, string>}
     */
    public function parHectare(string $cle, ?Zone $zone, int $anneeRecolte, bool $irrigue, float $deficitEauMm): array
    {
        $c = Referentiel::get($cle);
        $postes = [];
        $sources = [];

        foreach ($c['intrants'] as [$intrant, $qte]) {
            [$p, $src] = $this->prixIntrant($intrant, $zone);
            $label = PrixIntrant::INTRANTS[$intrant][0] ?? $intrant;
            $postes[$label] = ($postes[$label] ?? 0) + $qte * $p;
            $sources[$label] = $src;
        }

        $smag = Referentiel::smag($anneeRecolte);
        $postes['Main d\'œuvre'] = $c['mo'] * $smag;
        $sources['Main d\'œuvre'] = sprintf('%s jours × SMAG %d (%s DT/jour)', rtrim(rtrim(number_format($c['mo'], 2, ',', ''), '0'), ','), $anneeRecolte, number_format($smag, 3, ',', ' '));

        if (($c['tracteur'] ?? 0) > 0) {
            [$pm, $src] = $this->prixIntrant('machine_heure', $zone);
            $postes['Tracteur'] = $c['tracteur'] * $pm;
            $sources['Tracteur'] = $src;
        }

        if (!empty($c['moisson'])) {
            [$pmo, $src] = $this->prixIntrant('moisson_ha', $zone);
            $postes['Moisson'] = $pmo;
            $sources['Moisson'] = $src;
        }

        if ($irrigue && $deficitEauMm > 0) {
            [$pe, $src] = $this->prixIntrant('eau_irrigation', $zone);
            $m3 = $deficitEauMm * 10 / 0.8;
            $postes['Eau d\'irrigation'] = $m3 * $pe;
            $sources['Eau d\'irrigation'] = sprintf('%d m³/ha × %s DT', round($m3), $pe);
        }

        $postes = array_map(fn ($v) => round($v, 1), $postes);
        return ['parHa' => round(array_sum($postes), 1), 'postes' => $postes, 'sources' => $sources];
    }

    /** @return array{0: float, 1: string} prix, source */
    public function prixIntrant(string $intrant, ?Zone $zone): array
    {
        $k = $intrant . '|' . ($zone?->getId() ?? 0);
        if (isset($this->cachePrix[$k])) {
            return $this->cachePrix[$k];
        }
        $p = $this->prix->dernierPrix($intrant, $zone);
        if ($p) {
            $src = $p->getSource() . ($p->getZone() ? ' – ' . $p->getZone()->getNom() : '') . ' (' . $p->getDate()->format('m/Y') . ')';
            return $this->cachePrix[$k] = [$p->getPrix(), $src];
        }
        return $this->cachePrix[$k] = [self::PRIX_DEFAUT[$intrant] ?? 0.0, 'valeur par défaut'];
    }
}

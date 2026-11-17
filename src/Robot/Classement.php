<?php

namespace App\Robot;

use App\Entity\CodeTerrain;
use App\Entity\Zone;
use App\Repository\EstimationTerrainRepository;
use App\Repository\ZoneRepository;

/**
 * Classement des cultures et élevages du plus rentable au moins rentable, à 4 niveaux :
 *  - localité : le terrain de l'agriculteur (sa dernière analyse), sinon un terrain type de 1 ha dans la localité ;
 *  - gouvernorat : terrain type de 1 ha au centre du gouvernorat (même modèle que pour un vrai terrain) ;
 *  - Tunisie : moyenne des gouvernorats producteurs de chaque culture (ceux du référentiel, sinon tous) ;
 *  - monde : il n'existe pas de prix mondial fiable par culture dans nos données ; on classe donc par
 *    demande non couverte dans les pays suivis (là où il manque du produit, il y a des débouchés à l'export).
 * Cultures : bénéfice par hectare. Élevage : bénéfice pour un élevage type (effectif du référentiel).
 */
class Classement
{
    public function __construct(
        private RobotService $robot,
        private BilanZones $bilans,
        private ZoneRepository $zones,
        private EstimationTerrainRepository $estimations,
    ) {
    }

    /** @return list<array> lignes triées */
    public function zone(Zone $z): array
    {
        return self::trier(array_map([self::class, 'resume'], $this->robot->estimerZone($z)), $z, $this->bilans);
    }

    /** Le terrain de l'agriculteur, ramené à l'hectare. */
    public function terrain(CodeTerrain $t): array
    {
        $lignes = array_map(fn ($e) => $e->toArray(), $this->estimations->dernierLot($t));
        if (!$lignes) {
            return [];
        }
        $out = [];
        foreach ($lignes as $l) {
            $animal = ($l['details']['famille'] ?? '') === 'animal';
            $n = $animal ? 1 : max(0.01, $t->getSurface()); // l'élevage reste par élevage type
            $l['benefice'] /= $n;
            $l['cout'] /= $n;
            $l['productionEstimee'] /= $n;
            $out[] = self::resume($l);
        }
        return self::trier($out, $t->getGouvernorat(), $this->bilans);
    }

    /** Tunisie : moyenne des gouvernorats producteurs. */
    public function pays(): array
    {
        $gouvs = $this->zones->parNiveau('gouvernorat');
        $parGouv = [];
        foreach ($gouvs as $g) {
            foreach ($this->robot->estimerZone($g) as $l) {
                $parGouv[$l['cle']][ZoneRepository::normaliser($g->getNom())] = self::resume($l);
            }
        }
        $out = [];
        foreach ($parGouv as $cle => $lignes) {
            $prod = array_map([ZoneRepository::class, 'normaliser'], Referentiel::ZONES_PRODUCTION[$cle] ?? []);
            $sel = $prod ? array_intersect_key($lignes, array_flip($prod)) : $lignes;
            $sel = $sel ?: $lignes;
            $moy = fn (string $k) => array_sum(array_column($sel, $k)) / count($sel);
            $l = reset($sel);
            $out[] = array_merge($l, [
                'benefice' => round($moy('benefice')), 'tauxReussite' => round($moy('tauxReussite'), 1),
                'prix' => round($moy('prix'), 3), 'cout' => round($moy('cout')), 'production' => round($moy('production'), 3),
                'note' => sprintf('moyenne de %d gouvernorat(s)%s', count($sel), $prod ? ' producteurs' : ''),
            ]);
        }
        return self::trier($out, $this->zones->tunisie(), $this->bilans);
    }

    /** Monde (pays suivis) : demande non couverte. */
    public function monde(): array
    {
        $monde = $this->zones->monde();
        if (!$monde) {
            return [];
        }
        $out = [];
        foreach (Referentiel::tout() as $cle => $ref) {
            $b = $this->bilans->ligne($monde, $cle);
            if ($b['production'] === null || !$b['consoTotale']) {
                continue;
            }
            $manquent = [];
            foreach ($this->zones->parNiveau('pays') as $p) {
                $lp = $this->bilans->ligne($p, $cle);
                if ($lp['solde'] !== null && $lp['solde'] < 0) {
                    $manquent[] = ['pays' => $p->getNom(), 'manque' => -$lp['solde']];
                }
            }
            usort($manquent, fn ($a, $c) => $c['manque'] <=> $a['manque']);
            $manque = array_sum(array_column($manquent, 'manque'));
            $out[] = [
                'cle' => $cle, 'nom' => $ref['nom'], 'type' => $ref['type'], 'famille' => Referentiel::estAnimal($cle) ? 'animal' : 'vegetal',
                'couverture' => $b['tauxCouverture'], 'production' => $b['production'], 'consommation' => $b['consoTotale'],
                'manque' => round($manque), 'partManque' => round(100 * $manque / $b['consoTotale'], 1),
                'paysEnManque' => array_slice($manquent, 0, 3), 'source' => $b['sourceProduction'],
            ];
        }
        usort($out, fn ($a, $b) => $b['partManque'] <=> $a['partManque']);
        return $out;
    }

    private static function resume(array $l): array
    {
        $animal = ($l['details']['famille'] ?? '') === 'animal';
        return [
            'cle' => $l['cle'], 'nom' => $l['nom'], 'type' => $l['details']['type'] ?? '', 'famille' => $animal ? 'animal' : 'vegetal',
            'benefice' => round($l['benefice']), 'tauxReussite' => round($l['tauxReussite'], 1), 'prix' => $l['prix'], 'cout' => round($l['cout']),
            'production' => round($l['productionEstimee'], 3), 'exportation' => $l['exportation'],
            'saison' => [$l['cultivation'][0], $l['exploitation'][1]],
            'unite' => $animal ? ($l['details']['taille'] . ' ' . $l['details']['unite']) : 'ha',
            'affichage' => $l['details']['affichage'] ?? null,
        ];
    }

    /** Tri par bénéfice, cultures puis élevage, avec le niveau de saturation du marché local. */
    private static function trier(array $lignes, ?Zone $zone, BilanZones $bilans): array
    {
        foreach ($lignes as &$l) {
            $b = $zone ? $bilans->ligne($zone, $l['cle']) : null;
            $l['couverture'] = $b['tauxCouverture'] ?? null;
            $l['marche'] = $l['couverture'] === null ? 'inconnu' : ($l['couverture'] > 130 ? 'sature' : ($l['couverture'] < 90 ? 'manque' : 'equilibre'));
        }
        unset($l);
        usort($lignes, fn ($a, $b) => [$a['famille'] === 'animal', -$a['benefice']] <=> [$b['famille'] === 'animal', -$b['benefice']]);
        return $lignes;
    }
}

<?php

namespace App\Satellite;

/**
 * Reconnaissance de la culture d'un terrain à partir de sa courbe NDVI, dès les premières semaines.
 *
 * Deux sources de connaissance, combinées :
 *  1. Comparaison avec les profils de référence (ProfilsCultures) : pour chaque culture on cherche
 *     le meilleur ajustement (décalage de semis de ±3 semaines, densité ×0,6 à ×1,3) et on mesure
 *     l'écart (RMSE).
 *  2. k plus proches voisins sur les terrains RÉELS déclarés par les agriculteurs (apprentissage) :
 *     plus il y a de déclarations, plus leur poids augmente (jusqu'à 70 %).
 *
 * La confiance tient compte du nombre d'images et de la durée couverte : en début de saison, quand
 * toutes les cultures ressemblent encore à du sol nu, la confiance reste basse — c'est normal.
 */
final class ClassificateurCultures
{
    private const SIGMA = 0.045;         // tolérance d'écart NDVI (calibrée sur données simulées)
    private const DECALAGES = [-21, -14, -7, 0, 7, 14, 21];
    private const K = 2;
    private const MIN_POINTS = 3;

    /**
     * @param array<int, float> $serie      jour de campagne => NDVI du terrain à classer
     * @param list<array{cle: string, serie: array<int, float>}> $exemples terrains déclarés (apprentissage)
     * @return array{cle: string, nom: string, confiance: float, probabilites: array<string, float>, nbExemples: int}|null
     */
    public function classer(array $serie, array $exemples = []): ?array
    {
        ksort($serie);
        if (count($serie) < self::MIN_POINTS) {
            return null;
        }

        // 1. Profils de référence
        $prior = [];
        foreach (array_keys(ProfilsCultures::PROFILS) as $cle) {
            $rmse = $this->meilleurAjustementProfil($serie, $cle);
            $prior[$cle] = exp(-($rmse ** 2) / (2 * self::SIGMA ** 2));
        }
        $prior = self::normaliser($prior);

        // 2. Voisins réels : pour chaque culture, écart moyen avec ses K exemples les plus proches
        $parClasse = [];
        $nbEx = 0;
        foreach ($exemples as $ex) {
            $d = $this->distanceSeries($serie, $ex['serie']);
            if ($d !== null && ProfilsCultures::existe($ex['cle'])) {
                $parClasse[$ex['cle']][] = $d;
                ++$nbEx;
            }
        }
        $knn = [];
        foreach (array_keys(ProfilsCultures::PROFILS) as $cle) {
            if (empty($parClasse[$cle])) {
                $knn[$cle] = $prior[$cle]; // pas d'exemple : on garde l'a priori
                continue;
            }
            sort($parClasse[$cle]);
            $d = array_sum(array_slice($parClasse[$cle], 0, self::K)) / min(self::K, count($parClasse[$cle]));
            $knn[$cle] = exp(-($d ** 2) / (2 * self::SIGMA ** 2));
        }
        $knn = self::normaliser($knn);

        // poids des données réelles : augmente avec le nombre de terrains déclarés comparables
        $poids = $nbEx ? min(0.7, $nbEx / ($nbEx + 10)) : 0.0;
        $proba = [];
        foreach ($prior as $cle => $p) {
            $proba[$cle] = (1 - $poids) * $p + $poids * $knn[$cle];
        }
        $proba = self::normaliser($proba);
        arsort($proba);

        $top = array_key_first($proba);
        $jours = array_keys($serie);
        $couverture = min(1, count($serie) / 6) * min(1, (max($jours) - min($jours)) / 45);

        return [
            'cle' => $top,
            'nom' => ProfilsCultures::nom($top),
            // calibration (mesurée sur des terrains simulés) : la probabilité brute sous-estime la précision réelle
            'confiance' => round(1 - (1 - $proba[$top] * (0.4 + 0.6 * $couverture)) ** 2.5, 3),
            'probabilites' => array_map(fn ($p) => round($p, 3), array_slice($proba, 0, 5, true)),
            'nbExemples' => $nbEx,
        ];
    }

    private function meilleurAjustementProfil(array $serie, string $cle): float
    {
        $base = ProfilsCultures::PROFILS[$cle][1];
        $best = INF;
        foreach (self::DECALAGES as $s) {
            // densité a ajustée par moindres carrés sur (NDVI - base), bornée
            $num = 0.0;
            $den = 0.0;
            $theo = [];
            foreach ($serie as $t => $v) {
                $p = ProfilsCultures::ndvi($cle, $t - $s) - $base;
                $theo[$t] = $p;
                $num += $p * ($v - $base);
                $den += $p * $p;
            }
            $a = $den > 1e-6 ? max(0.6, min(1.3, $num / $den)) : 1.0;
            $err = 0.0;
            foreach ($serie as $t => $v) {
                $err += ($v - ($base + $a * $theo[$t])) ** 2;
            }
            $best = min($best, sqrt($err / count($serie)));
        }
        return $best;
    }

    /** RMSE entre deux courbes (interpolation linéaire, meilleur décalage), null si trop peu de recouvrement. */
    private function distanceSeries(array $a, array $b): ?float
    {
        ksort($b);
        if (count($b) < 2) {
            return null;
        }
        $best = null;
        foreach (self::DECALAGES as $s) {
            $err = 0.0;
            $n = 0;
            foreach ($a as $t => $v) {
                $vb = self::interpoler($b, $t - $s);
                if ($vb !== null) {
                    $err += ($v - $vb) ** 2;
                    ++$n;
                }
            }
            if ($n >= self::MIN_POINTS) {
                $r = sqrt($err / $n);
                $best = $best === null ? $r : min($best, $r);
            }
        }
        return $best;
    }

    private static function interpoler(array $serie, float $t): ?float
    {
        $prevT = $prevV = null;
        foreach ($serie as $ti => $vi) {
            if ($ti == $t) {
                return $vi;
            }
            if ($ti > $t) {
                if ($prevT === null || $ti - $prevT > 30) {
                    return null; // trou trop grand ou hors période
                }
                return $prevV + ($vi - $prevV) * ($t - $prevT) / ($ti - $prevT);
            }
            $prevT = $ti;
            $prevV = $vi;
        }
        return null;
    }

    private static function normaliser(array $p): array
    {
        $s = array_sum($p);
        if ($s <= 0) {
            return array_map(fn () => 1 / count($p), $p);
        }
        return array_map(fn ($x) => $x / $s, $p);
    }
}

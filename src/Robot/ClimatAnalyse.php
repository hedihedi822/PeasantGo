<?php

namespace App\Robot;

/**
 * Évalue une culture sur un lieu à partir de la météo réelle des années passées, sur exactement la période
 * de culture (même date de semis, même durée) :
 *   - eau : pluie efficace (pluie × 0,8, plafonnée à 40 mm/jour) comparée au besoin de la culture
 *     (si le terrain est irrigué, l'eau n'est plus limitante mais le déficit est compté dans le coût) ;
 *   - gel : jours sous la température critique de la culture (après la levée) ;
 *   - chaleur : jours au-dessus du seuil de stress ;
 *   - degrés-jours : la culture arrive-t-elle à maturité ?
 * Chaque année reçoit un score 0–1 ; une année « réussie » a un score ≥ 0,55.
 */
final class ClimatAnalyse
{
    public const SEUIL_REUSSITE = 0.55;

    /**
     * @param array<string, array{tmax: float, tmin: float, pluie: float}> $jours météo indexée par date Y-m-d
     * @param list<int> $annees années de semis à tester
     */
    public function evaluer(array $jours, array $culture, int $moisSemis, int $jourSemis, array $annees, bool $irrigue): array
    {
        $resultats = [];
        foreach ($annees as $y) {
            $debut = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $moisSemis, min($jourSemis, 28)));
            $r = $this->annee($jours, $culture, $debut, $irrigue);
            if ($r) {
                $resultats[] = $r + ['annee' => $y];
            }
        }
        if (!$resultats) {
            return ['nbAnnees' => 0, 'pReussite' => null, 'scoreMoyen' => null, 'annees' => []];
        }

        $n = count($resultats);
        $succes = count(array_filter($resultats, fn ($r) => $r['score'] >= self::SEUIL_REUSSITE));
        $moy = fn (string $k) => round(array_sum(array_column($resultats, $k)) / $n, 1);

        return [
            'nbAnnees' => $n,
            'anneesReussies' => $succes,
            // lissage de Laplace : évite 0 % ou 100 % avec peu d'années
            'pReussite' => ($succes + 1) / ($n + 2),
            'scoreMoyen' => array_sum(array_column($resultats, 'score')) / $n,
            'pluieMoyenne' => $moy('pluie'),
            'besoinEau' => $culture['eau'],
            'deficitMoyen' => $moy('deficit'),
            'joursGelMoyen' => $moy('gel'),
            'joursChaleurMoyen' => $moy('chaleur'),
            'gddMoyen' => $moy('gdd'),
            'gddRequis' => $culture['gdd'],
            'principalRisque' => $this->risque($resultats, $irrigue),
            'annees' => $resultats,
        ];
    }

    private function annee(array $jours, array $c, \DateTimeImmutable $debut, bool $irrigue): ?array
    {
        $pluie = $gdd = 0.0;
        $gel = $chaleur = $connus = 0;
        $cycle = (int) $c['cycle'];
        for ($i = 0; $i < $cycle; ++$i) {
            $d = $debut->modify("+$i days")->format('Y-m-d');
            if (!isset($jours[$d])) {
                continue;
            }
            ++$connus;
            $j = $jours[$d];
            $pluie += min($j['pluie'], 40) * 0.8;
            $gdd += max(0, ($j['tmax'] + $j['tmin']) / 2 - $c['tBase']);
            if ($i > 15 && $j['tmin'] < $c['tGel']) {
                ++$gel;
            }
            if ($j['tmax'] > $c['tChaleur']) {
                ++$chaleur;
            }
        }
        if ($connus < 0.9 * $cycle) {
            return null; // année incomplète
        }
        $k = $cycle / $connus; // extrapole les quelques jours manquants
        $pluie *= $k;
        $gdd *= $k;

        $deficit = max(0, $c['eau'] - $pluie);
        $ratio = $pluie / max(1, $c['eau']);
        $scoreEau = $irrigue ? 1.0 : max(0, min(1, ($ratio - 0.35) / 0.5));
        $penGel = min(0.6, 0.12 * $gel);
        $penChaleur = min(0.5, 0.04 * $chaleur);
        $ratioGdd = $gdd / max(1, $c['gdd']);
        $penGdd = $ratioGdd < 0.85 ? min(0.6, (0.85 - $ratioGdd) * 1.5) : 0;

        $score = $scoreEau * (1 - $penGel) * (1 - $penChaleur) * (1 - $penGdd);

        return [
            'score' => round($score, 3),
            'pluie' => round($pluie),
            'deficit' => round($deficit),
            'gel' => $gel,
            'chaleur' => $chaleur,
            'gdd' => round($gdd),
            'facteurs' => ['eau' => round($scoreEau, 2), 'gel' => round(1 - $penGel, 2), 'chaleur' => round(1 - $penChaleur, 2), 'maturite' => round(1 - $penGdd, 2)],
        ];
    }

    /** Le facteur qui fait le plus perdre en moyenne. */
    private function risque(array $resultats, bool $irrigue): string
    {
        $noms = ['eau' => $irrigue ? 'eau' : 'manque de pluie (pas d\'irrigation déclarée)', 'gel' => 'gel', 'chaleur' => 'fortes chaleurs', 'maturite' => 'chaleur insuffisante pour mûrir'];
        $pertes = [];
        foreach (array_keys($noms) as $k) {
            $pertes[$k] = array_sum(array_map(fn ($r) => 1 - $r['facteurs'][$k], $resultats));
        }
        arsort($pertes);
        $k = array_key_first($pertes);
        return $pertes[$k] / count($resultats) < 0.05 ? 'aucun risque climatique majeur' : $noms[$k];
    }
}

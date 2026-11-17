<?php

namespace App\Robot;

/**
 * Évalue un élevage sur un lieu à partir de la météo réelle des années passées, sur la même période d'élevage :
 *   - chaleur : jours au-dessus du seuil de stress (mortalité, baisse du lait, de la ponte, du nectar) ;
 *   - froid : jours sous le seuil (volailles) ;
 *   - pluie : pluie des mois précédant la mise en place, comparée au besoin (pâturage pour les ruminants,
 *     floraison pour les abeilles).
 * Chaque année reçoit un score 0–1 ; une année « réussie » a un score ≥ 0,55 (comme pour les cultures).
 */
final class ClimatAnimal
{
    public const SEUIL_REUSSITE = 0.55;

    /**
     * @param array<string, array{tmax: float, tmin: float, pluie: float}> $jours
     * @param list<int> $annees
     */
    public function evaluer(array $jours, array $a, int $mois, int $jour, array $annees): array
    {
        $res = [];
        foreach ($annees as $y) {
            $debut = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $mois, min($jour, 28)));
            $r = $this->annee($jours, $a, $debut);
            if ($r) {
                $res[] = $r + ['annee' => $y];
            }
        }
        if (!$res) {
            return ['nbAnnees' => 0, 'pReussite' => null, 'scoreMoyen' => null, 'annees' => []];
        }
        $n = count($res);
        $succes = count(array_filter($res, fn ($r) => $r['score'] >= self::SEUIL_REUSSITE));
        $moy = fn (string $k) => round(array_sum(array_column($res, $k)) / $n, 1);

        $risques = [];
        if ($moy('chaleur') >= 5) {
            $risques[] = sprintf('chaleur (%s j > %d °C)', $moy('chaleur'), $a['tChaleur']);
        }
        if (isset($a['tFroid']) && $moy('froid') >= 5) {
            $risques[] = sprintf('froid (%s j < %d °C)', $moy('froid'), $a['tFroid']);
        }
        if (isset($a['pluieBesoin']) && $moy('pluie') < 0.7 * $a['pluieBesoin']) {
            $risques[] = sprintf('sécheresse (%s mm de pluie avant la saison pour %d mm utiles)', $moy('pluie'), $a['pluieBesoin']);
        }

        return [
            'nbAnnees' => $n,
            'anneesReussies' => $succes,
            'pReussite' => ($succes + 1) / ($n + 2),
            'scoreMoyen' => array_sum(array_column($res, 'score')) / $n,
            'joursChaleurMoyen' => $moy('chaleur'),
            'joursFroidMoyen' => $moy('froid'),
            'pluieMoyenne' => isset($a['pluieBesoin']) ? $moy('pluie') : null,
            'besoinEau' => $a['pluieBesoin'] ?? null,
            'principalRisque' => $risques ? implode(', ', $risques) : 'aucun risque climatique majeur',
            'annees' => $res,
        ];
    }

    private function annee(array $jours, array $a, \DateTimeImmutable $debut): ?array
    {
        $chaleur = $froid = $vus = 0;
        for ($i = 0; $i < $a['cycle']; ++$i) {
            $j = $jours[$debut->modify("+$i days")->format('Y-m-d')] ?? null;
            if (!$j) {
                continue;
            }
            ++$vus;
            if ($j['tmax'] > $a['tChaleur']) {
                ++$chaleur;
            }
            if (isset($a['tFroid']) && $j['tmin'] < $a['tFroid']) {
                ++$froid;
            }
        }
        if ($vus < 0.8 * $a['cycle']) {
            return null; // période incomplète dans l'historique
        }
        $pluie = 0.0;
        if (isset($a['pluieBesoin'])) {
            $de = $debut->modify('-' . $a['pluieMois'] . ' months');
            for ($d = $de; $d < $debut; $d = $d->modify('+1 day')) {
                $pluie += min(40, ($jours[$d->format('Y-m-d')]['pluie'] ?? 0)) * 0.8;
            }
        }
        $score = 1.0;
        $score -= min(0.5, $chaleur * $a['perteChaleur'] * 2);
        $score -= min(0.3, $froid * ($a['perteFroid'] ?? 0) * 2);
        if (isset($a['pluieBesoin'])) {
            $score -= 0.45 * max(0, 1 - $pluie / $a['pluieBesoin']);
        }
        return ['score' => max(0, round($score, 3)), 'chaleur' => $chaleur, 'froid' => $froid, 'pluie' => round($pluie)];
    }
}

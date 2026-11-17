<?php

namespace App\Service;

use App\Entity\StatistiqueSnapshot;
use App\Repository\StatistiqueSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Compare les statistiques actuelles du catalogue avec la dernière publication
 * et prévient TOUS les utilisateurs des nouveautés.
 *
 * Lancé chaque jour par cron (php bin/console app:statistiques:notifier)
 * ou à la main depuis le panneau admin (« Publier les statistiques »).
 */
class StatistiquesService
{
    /** Indicateurs suivis : clé => [libellé, unité, décimales] */
    public const INDICATEURS = [
        'taux' => ['taux de réussite', '%', 1],
        'prix' => ['prix', ' DT/kg', 2],
        'cout' => ['coût', ' DT/ha', 0],
        'exces' => ['% excès', '%', 1],
        'confiance' => ['confiance', '%', 1],
        'production' => ['production', ' t', 0],
    ];

    private const MAX_LIGNES = 8;

    public function __construct(
        private CatalogueService $catalogue,
        private NotificationService $notifications,
        private StatistiqueSnapshotRepository $snapshots,
        private EntityManagerInterface $em,
    ) {
    }

    /** Valeurs actuelles (niveau national), indexées par "idP-idS". */
    public function snapshotActuel(): array
    {
        $t = $this->catalogue->getTableaux(true);
        $out = [];
        foreach ([...$t['vegetal'], ...$t['animal']] as $r) {
            $n = $r['niveaux']['national'];
            $out[$r['idP'] . '-' . ($r['idS'] ?? 0)] = [
                'libelle' => $r['sousType'] . ($r['numeroSaison'] ? ' (saison ' . $r['numeroSaison'] . ')' : ''),
                'taux' => $n['taux'] ?? null,
                'prix' => $n['prix'] ?? null,
                'cout' => $n['cout'] ?? null,
                'exces' => $n['exces'] ?? null,
                'confiance' => $n['confiance'] ?? null,
                'production' => $n['production'] ?? null,
            ];
        }
        return $out;
    }

    /**
     * @return list<array{libelle: string, indicateur: string, avant: ?float, apres: ?float, variation: ?float}>
     *         variation en % (null pour un nouveau produit)
     */
    public function comparer(array $avant, array $apres, float $seuil): array
    {
        $changements = [];
        foreach ($apres as $cle => $vals) {
            if (!isset($avant[$cle])) {
                $changements[] = ['libelle' => $vals['libelle'], 'indicateur' => 'nouveau', 'avant' => null, 'apres' => null, 'variation' => null];
                continue;
            }
            foreach (array_keys(self::INDICATEURS) as $k) {
                $a = $avant[$cle][$k] ?? null;
                $b = $vals[$k] ?? null;
                if ($a === null || $b === null || (float) $a === (float) $b) {
                    continue;
                }
                $variation = (float) $a == 0.0 ? 100.0 : ($b - $a) / abs($a) * 100;
                if (abs($variation) >= $seuil) {
                    $changements[] = ['libelle' => $vals['libelle'], 'indicateur' => $k, 'avant' => (float) $a, 'apres' => (float) $b, 'variation' => round($variation, 1)];
                }
            }
        }
        // Les plus grosses variations d'abord, les nouveaux produits en tête
        usort($changements, fn ($x, $y) => ($y['variation'] === null) <=> ($x['variation'] === null) ?: abs($y['variation'] ?? 0) <=> abs($x['variation'] ?? 0));
        return $changements;
    }

    /**
     * @return array{statut: string, changements: array, envoi: ?array}
     *   statut : "initial" (première photo), "aucun" (rien de nouveau), "publie", "simulation"
     */
    public function publier(float $seuil = 5.0, bool $email = true, bool $forcer = false, bool $simulation = false): array
    {
        $actuel = $this->snapshotActuel();
        $dernier = $this->snapshots->findDernier();

        if (!$dernier) {
            $changements = [];
            $titre = 'Les statistiques agricoles de la saison sont disponibles';
            $message = sprintf("%d produits et saisons sont suivis sur PeasantGo.AI : taux de réussite, prix, coûts, exportation et estimation d'excès.\nConsultez-les dès maintenant sur votre page d'accueil.", count($actuel));
        } else {
            $changements = $this->comparer($dernier->getDonnees(), $actuel, $seuil);
            if (!$changements && !$forcer) {
                return ['statut' => 'aucun', 'changements' => [], 'envoi' => null];
            }
            $titre = $changements
                ? sprintf('Nouvelles statistiques : %d changement(s) depuis le %s', count($changements), $dernier->getCreatedAt()->format('d/m/Y'))
                : 'Statistiques agricoles mises à jour';
            $message = $changements ? $this->formater($changements) : "Les statistiques ont été vérifiées : pas de variation importante depuis la dernière mise à jour.";
        }

        if ($simulation) {
            return ['statut' => 'simulation', 'changements' => $changements, 'envoi' => null, 'titre' => $titre, 'message' => $message];
        }

        $this->em->persist(new StatistiqueSnapshot($actuel));
        $this->em->flush();

        $envoi = $this->notifications->envoyerATous('statistiques', $titre, $message, '/home/user', $email);

        return ['statut' => $dernier ? 'publie' : 'initial', 'changements' => $changements, 'envoi' => $envoi, 'titre' => $titre, 'message' => $message];
    }

    public function formater(array $changements): string
    {
        $lignes = [];
        foreach (array_slice($changements, 0, self::MAX_LIGNES) as $c) {
            if ($c['indicateur'] === 'nouveau') {
                $lignes[] = '• ' . $c['libelle'] . ' : nouveau dans le catalogue';
                continue;
            }
            [$nom, $unite, $dec] = self::INDICATEURS[$c['indicateur']];
            $lignes[] = sprintf('• %s : %s %s → %s%s (%s%s %%)',
                $c['libelle'], $nom,
                self::nombre($c['avant'], $dec), self::nombre($c['apres'], $dec), $unite,
                $c['variation'] > 0 ? '+' : '', self::nombre($c['variation'], 1));
        }
        if (count($changements) > self::MAX_LIGNES) {
            $lignes[] = sprintf('… et %d autre(s) changement(s).', count($changements) - self::MAX_LIGNES);
        }
        return implode("\n", $lignes);
    }

    private static function nombre(float $v, int $dec): string
    {
        return number_format($v, $dec, ',', ' ');
    }
}

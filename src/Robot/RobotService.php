<?php

namespace App\Robot;

use App\Entity\CodeTerrain;
use App\Entity\EstimationTerrain;
use App\IA\AnalyseurIA;
use App\Meteo\MeteoService;
use App\Repository\EstimationTerrainRepository;
use App\Repository\ModeleCalibrationRepository;
use App\Repository\ObservationSatelliteRepository;
use App\Satellite\ProfilsCultures;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Zone;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Robot PeasantGo.AI : pour un terrain (ou un terrain type d'une zone), calcule une ligne par culture et par élevage
 * (saison, taux de réussite, production, export, coût, prix, bénéfice), enregistre le résultat
 * et demande à l'IA un conseil et une lecture de l'image satellite.
 */
class RobotService
{
    private const ANNEES_CLIMAT = 8;
    private const NB_RECOMMANDEES = 3;

    public function __construct(
        private EntityManagerInterface $em,
        private Localisation $localisation,
        private MeteoService $meteo,
        private Saisons $saisons,
        private ClimatAnalyse $climat,
        private EstimateurCout $couts,
        private EstimateurPrix $prix,
        private BilanZones $bilans,
        private ModeleCalibrationRepository $calibrations,
        private ObservationSatelliteRepository $observations,
        private EstimationTerrainRepository $estimations,
        private AnalyseurIA $ia,
        private LoggerInterface $logger,
        private ClimatAnimal $climatAnimal,
        private AlerteService $alertes,
        private CacheInterface $cache,
    ) {
    }

    /**
     * Analyse complète d'un vrai terrain : une ligne par culture et par élevage, enregistrées en base.
     * @return array{estimations: EstimationTerrain[], meteo: array, erreurs: list<string>}
     */
    public function analyserTerrain(CodeTerrain $t, bool $avecIA = true): array
    {
        $this->localisation->localiser($t);
        if (!$t->getPoint()) {
            throw new \RuntimeException('Localité introuvable : renseignez la localité du terrain ou dessinez son contour.');
        }
        $ancien = $this->estimations->dernierLot($t);
        $site = Site::depuisTerrain($t);
        $principale = $t->getCulturePrincipale();
        $clePrincipale = $principale ? ProfilsCultures::depuisDeclaration($principale->getCultureCle(), $principale->getCultureNom()) : null;
        $r = $this->estimerSite($site, null, $clePrincipale);

        $lot = (new \DateTime())->format('YmdHis');
        $lignes = [];
        foreach ($r['lignes'] as $l) {
            $e = (new EstimationTerrain($t, $lot, $l['cle'], $l['nom']))->remplir($l);
            $e->setRecommande($l['recommande']);
            $this->em->persist($e);
            $lignes[] = $e;
        }
        $this->em->flush();
        $this->estimations->purger($t);

        $erreurs = $r['erreurs'];
        if ($avecIA) {
            try {
                $sat = null;
                $derniere = $this->derniereImage($t);
                if ($derniere) {
                    $sat = $this->ia->analyserImage($t, $derniere, $this->serieNdvi($t))->toArray();
                }
                $this->ia->conseil($t, $lignes, $r['meteo'], $sat);
            } catch (\Throwable $e) {
                $erreurs[] = 'Analyse IA : ' . $e->getMessage();
            }
        }
        try {
            $this->alertes->apresAnalyse($t, $ancien, $lignes, $r['meteo']);
        } catch (\Throwable $e) {
            $this->logger->error('Alertes non envoyées', ['terrain' => $t->getCodeTerrain(), 'erreur' => $e->getMessage()]);
        }

        return ['estimations' => $lignes, 'meteo' => $r['meteo'], 'erreurs' => $erreurs];
    }

    /**
     * Estimations d'un terrain type de 1 ha au centre d'une zone (sans rien enregistrer), gardées 12 h en cache.
     * Sert à l'accueil des visiteurs et au classement des cultures par zone.
     * @param ?list<string> $cles cultures / élevages à estimer (null = tous)
     * @return list<array> lignes au format EstimationTerrain::toArray()
     */
    public function estimerZone(Zone $z, ?array $cles = null): array
    {
        $cle = sprintf('robot_zone_%d_%s_%s', $z->getId(), (new \DateTime())->format('Ymd') . ((int) date('H') < 12 ? 'm' : 's'), md5(implode(',', $cles ?? ['*'])));
        return $this->cache->get($cle, function (ItemInterface $item) use ($z, $cles) {
            $item->expiresAfter(12 * 3600);
            if ($z->getLatitude() === null || $z->getLongitude() === null) {
                return [];
            }
            $r = $this->estimerSite(Site::typeDansZone($z), $cles);
            return array_map([self::class, 'versTableau'], $r['lignes']);
        });
    }

    /**
     * Cœur du robot : une ligne par culture / élevage pour un lieu.
     * @return array{lignes: list<array>, meteo: array, erreurs: list<string>}
     */
    public function estimerSite(Site $site, ?array $cles = null, ?string $perenneAutorisee = null): array
    {
        $erreurs = [];
        $prevision = [];
        try {
            $prevision = $this->meteo->prevision($site->lat, $site->lon);
        } catch (\Throwable $e) {
            $erreurs[] = 'Prévisions météo indisponibles : ' . $e->getMessage();
        }
        $jours = [];
        try {
            $fin = new \DateTimeImmutable('-6 days');
            $debut = new \DateTimeImmutable(((int) $fin->format('Y') - self::ANNEES_CLIMAT - 1) . '-01-01');
            foreach ($this->meteo->historique($site->lat, $site->lon, $debut, $fin) as $j) {
                $jours[$j['date']] = $j;
            }
        } catch (\Throwable $e) {
            $erreurs[] = 'Historique météo indisponible : ' . $e->getMessage();
        }

        $vigueur = $site->terrain ? $this->vigueurSatellite($site->terrain) : ['facteur' => 1.0, 'risque' => 1.0, 'ndviMax' => null, 'note' => 'terrain type (pas d\'image satellite)'];
        $auj = new \DateTimeImmutable('today');

        $lignes = [];
        foreach (Referentiel::tout() as $cle => $ref) {
            if ($cles !== null && !in_array($cle, $cles, true)) {
                continue;
            }
            if (!empty($ref['perenne']) && $cle !== $perenneAutorisee) {
                continue; // un verger ne se plante pas pour une saison
            }
            try {
                $lignes[] = Referentiel::estAnimal($cle)
                    ? $this->estimerAnimal($site, $cle, $ref, $jours, $prevision, $auj)
                    : $this->estimerCulture($site, $cle, $ref, $jours, $prevision, $vigueur, $auj);
            } catch (\Throwable $e) {
                $erreurs[] = Referentiel::nom($cle) . ' : ' . $e->getMessage();
                $this->logger->error('Estimation impossible', ['culture' => $cle, 'erreur' => $e->getMessage()]);
            }
        }

        // Recommandations : les 3 cultures et l'élevage les plus rentables (bénéfice positif, réussite ≥ 40 %)
        usort($lignes, fn ($a, $b) => $b['benefice'] <=> $a['benefice']);
        $nb = ['vegetal' => 0, 'animal' => 0];
        $max = ['vegetal' => self::NB_RECOMMANDEES, 'animal' => 1];
        foreach ($lignes as &$l) {
            $f = $l['details']['famille'];
            $l['recommande'] = $l['benefice'] > 0 && $l['tauxReussite'] >= 40 && $nb[$f] < $max[$f];
            if ($l['recommande']) {
                ++$nb[$f];
            }
        }
        unset($l);

        return ['lignes' => $lignes, 'meteo' => $prevision, 'erreurs' => $erreurs];
    }

    /** Ligne calculée → même format que EstimationTerrain::toArray(). */
    public static function versTableau(array $l): array
    {
        return [
            'cle' => $l['cle'], 'nom' => $l['nom'],
            'numeroSaison' => $l['numeroSaison'], 'saisonsAn' => $l['saisonsAn'],
            'cultivation' => [$l['debutCultivation']->format('Y-m-d'), $l['finCultivation']->format('Y-m-d')],
            'exploitation' => [$l['debutExploitation']->format('Y-m-d'), $l['finExploitation']->format('Y-m-d')],
            'tauxReussite' => round($l['tauxReussite'], 1), 'productionEstimee' => round($l['productionEstimee'], 2),
            'exportation' => $l['exportation'], 'cout' => round($l['cout']), 'prix' => round($l['prix'], 3),
            'benefice' => round($l['benefice']), 'recommande' => $l['recommande'] ?? false, 'details' => $l['details'],
        ];
    }

    /** Années passées dont la même période (même date de départ, même durée) est entièrement connue. */
    private function anneesComparables(\DateTimeInterface $debut, int $cycle, \DateTimeImmutable $auj): array
    {
        $annees = [];
        for ($y = (int) $auj->format('Y') - 1; count($annees) < self::ANNEES_CLIMAT && $y > (int) $auj->format('Y') - 12; --$y) {
            $finCycle = (new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, (int) $debut->format('n'), min(28, (int) $debut->format('j')))))->modify('+' . $cycle . ' days');
            if ($finCycle < $auj->modify('-6 days')) {
                $annees[] = $y;
            }
        }
        return $annees;
    }

    private function estimerCulture(Site $site, string $cle, array $ref, array $jours, array $prevision, array $vigueur, \DateTimeImmutable $auj): array
    {
        $saison = $this->saisons->prochaine($cle, $auj);
        $debut = $saison['debutCultivation'];
        $anneeRecolte = (int) $saison['debutExploitation']->format('Y');
        $irrigue = $site->irriguePour($ref);

        // ---- Climat des années passées sur la même période ----
        $annees = $this->anneesComparables($debut, $ref['cycle'], $auj);
        $climat = $jours ? $this->climat->evaluer($jours, $ref, (int) $debut->format('n'), (int) $debut->format('j'), $annees, $irrigue) : ['nbAnnees' => 0, 'pReussite' => null, 'scoreMoyen' => null];
        $pClimat = $climat['pReussite'] ?? 0.6;
        $score = $climat['scoreMoyen'] ?? 0.65;

        // ---- Prévisions 16 jours (si le semis tombe dedans) ----
        [$facteurPrev, $notesPrev] = $this->effetPrevision($prevision, $ref, $debut);

        // ---- Apprentissage (récoltes déclarées dans le gouvernorat, sinon national) ----
        $cal = $this->calibrations->pour($cle, $site->gouvernorat);
        $p = $pClimat * $facteurPrev * $vigueur['risque'];
        if ($cal && $cal->getNbRecoltes() > 0) {
            $k = 6; // poids de l'a priori « climat » en nombre de récoltes équivalentes
            $p = ($p * $k + $cal->getNbReussites()) / ($k + $cal->getNbRecoltes());
        }
        $taux = max(1, min(99, $p * 100));

        // ---- Production ----
        $base = $ref['rendement'][$irrigue ? 1 : 0];
        $rendement = $base * (0.35 + 0.65 * $score) * $vigueur['facteur'];
        if ($cal && $cal->getNbRecoltes() > 0) {
            $w = $cal->getNbRecoltes() / ($cal->getNbRecoltes() + 3);
            $rendement *= 1 + $w * ($cal->getFacteurRendement() - 1);
        }
        $production = $rendement * $site->surface;

        // ---- Coût ----
        $cout = $this->couts->parHectare($cle, $site->zonePrix(), $anneeRecolte, $irrigue, (float) ($climat['deficitMoyen'] ?? 0));
        $coutTotal = $cout['parHa'] * $site->surface;
        if ($cal && $cal->getNbCouts() > 0) {
            $w = $cal->getNbCouts() / ($cal->getNbCouts() + 3);
            $coutTotal *= 1 + $w * ($cal->getFacteurCout() - 1);
        }
        $coutKg = $production > 0 ? $coutTotal / ($production * 1000) : 0;

        // ---- Prix dans la localité ----
        $px = $this->prix->estimer($cle, $site->localite, $site->gouvernorat, $saison['debutExploitation'], $coutKg, $site->terrain);

        return $this->finaliser($cle, $ref, $saison, $taux, $production, $coutTotal, $px, [
            'famille' => 'vegetal',
            'saisonSource' => $saison['source'],
            'irrigue' => $irrigue,
            'surface' => $site->surface,
            'rendementHa' => round($rendement, 2),
            'rendementBase' => $base,
            'climat' => self::sansAnnees($climat),
            'prevision' => $notesPrev,
            'satellite' => $vigueur,
            'apprentissage' => $this->resumeCal($cal),
            'cout' => $cout + ['total' => round($coutTotal)],
        ]);
    }

    private function estimerAnimal(Site $site, string $cle, array $ref, array $jours, array $prevision, \DateTimeImmutable $auj): array
    {
        $saison = $this->saisons->prochaine($cle, $auj);
        $debut = $saison['debutCultivation'];
        $anneeVente = (int) $saison['debutExploitation']->format('Y');
        $taille = max(1, (int) ($site->tailles[$cle] ?? $ref['tailleDefaut']));

        $annees = $this->anneesComparables($debut, $ref['cycle'], $auj);
        $climat = $jours ? $this->climatAnimal->evaluer($jours, $ref, (int) $debut->format('n'), (int) $debut->format('j'), $annees) : ['nbAnnees' => 0, 'pReussite' => null, 'scoreMoyen' => null];
        $p = $climat['pReussite'] ?? 0.65;
        $score = $climat['scoreMoyen'] ?? 0.7;

        // Prévisions : vague de chaleur au départ de la bande
        $notesPrev = [];
        foreach ($prevision as $j) {
            $d = new \DateTime($j['date']);
            if ($d >= $debut && $d <= (clone $debut)->modify('+20 days') && $j['tmax'] > $ref['tChaleur']) {
                $p *= 0.97;
                $notesPrev[] = sprintf('chaleur prévue le %s (%.1f °C)', $d->format('d/m'), $j['tmax']);
            }
        }

        $cal = $this->calibrations->pour($cle, $site->gouvernorat);
        if ($cal && $cal->getNbRecoltes() > 0) {
            $p = ($p * 6 + $cal->getNbReussites()) / (6 + $cal->getNbRecoltes());
        }
        $taux = max(1, min(99, $p * 100));

        $parUnite = $ref['production'] * (1 - $ref['mortalite']) * (0.6 + 0.4 * $score);
        if ($cal && $cal->getNbRecoltes() > 0) {
            $w = $cal->getNbRecoltes() / ($cal->getNbRecoltes() + 3);
            $parUnite *= 1 + $w * ($cal->getFacteurRendement() - 1);
        }
        $production = $parUnite * $taille;

        $cout = $this->couts->parHectare($cle, $site->zonePrix(), $anneeVente, false, 0);
        $coutTotal = $cout['parHa'] * $taille;
        if ($cal && $cal->getNbCouts() > 0) {
            $w = $cal->getNbCouts() / ($cal->getNbCouts() + 3);
            $coutTotal *= 1 + $w * ($cal->getFacteurCout() - 1);
        }
        $coutKg = $production > 0 ? $coutTotal / ($production * 1000) : 0;
        $px = $this->prix->estimer($cle, $site->localite, $site->gouvernorat, $saison['debutExploitation'], $coutKg, $site->terrain);

        // Aïd al-Adha : la demande d'agneaux et leur prix montent dans les semaines qui précèdent la fête
        if (!empty($ref['aid'])) {
            $aid = Referentiel::aidApres($saison['debutExploitation']);
            if ($aid) {
                $ecart = ($aid->getTimestamp() - $saison['finExploitation']->getTimestamp()) / 86400;
                if ($ecart >= -5 && $ecart <= 30) {
                    $px['prix'] = round($px['prix'] * 1.2, 3);
                    $px['details']['etapes'][] = sprintf('Vente juste avant l\'Aïd al-Adha (≈ %s) : prix × 1,20.', $aid->format('d/m/Y'));
                } else {
                    $px['details']['etapes'][] = sprintf('Vente loin de l\'Aïd al-Adha (≈ %s) : pas de majoration.', $aid->format('d/m/Y'));
                }
            }
        }

        return $this->finaliser($cle, $ref, $saison, $taux, $production, $coutTotal, $px, [
            'famille' => 'animal',
            'saisonSource' => $saison['source'],
            'irrigue' => false,
            'taille' => $taille,
            'unite' => $ref['unite'],
            'affichage' => $ref['affichage'],
            'rendementHa' => round($parUnite, 6), // par animal / ruche
            'rendementBase' => $ref['production'],
            'climat' => self::sansAnnees($climat),
            'prevision' => $notesPrev,
            'satellite' => ['note' => 'non utilisé pour l\'élevage'],
            'apprentissage' => $this->resumeCal($cal),
            'cout' => $cout + ['total' => round($coutTotal), 'parUnite' => $cout['parHa']],
        ]);
    }

    private function finaliser(string $cle, array $ref, array $saison, float $taux, float $production, float $coutTotal, array $px, array $details): array
    {
        $pays = $this->bilans->soldePays($cle);
        $exportation = $pays && $pays['solde'] !== null && $pays['consoTotale'] && $pays['solde'] > 0.05 * $pays['consoTotale'];
        // Bénéfice espéré : en cas d'échec on récupère en moyenne 30 % de la production prévue
        $recette = $production * 1000 * $px['prix'] * (0.3 + 0.7 * $taux / 100);

        return $saison + [
            'cle' => $cle,
            'nom' => $ref['nom'],
            'tauxReussite' => $taux,
            'productionEstimee' => $production,
            'exportation' => $exportation,
            'cout' => $coutTotal,
            'prix' => $px['prix'],
            'benefice' => $recette - $coutTotal,
            'recommande' => false,
            'details' => $details + [
                'type' => $ref['type'],
                'prix' => $px['details'],
                'export' => $pays ? ['solde' => $pays['solde'], 'destinations' => array_slice($pays['export'], 0, 3), 'source' => $pays['sourceProduction']] : null,
            ],
        ];
    }

    private static function sansAnnees(array $climat): array
    {
        unset($climat['annees']);
        return $climat;
    }

    private function resumeCal(?\App\Entity\ModeleCalibration $cal): ?array
    {
        return $cal ? ['recoltes' => $cal->getNbRecoltes(), 'reussites' => $cal->getNbReussites(), 'facteurRendement' => round($cal->getFacteurRendement(), 3), 'zone' => $cal->getZone()?->getNom() ?? 'Tunisie'] : null;
    }

    /** @return array{0: float, 1: list<string>} */
    private function effetPrevision(array $prevision, array $ref, \DateTime $semis): array
    {
        $f = 1.0;
        $notes = [];
        foreach ($prevision as $j) {
            $d = new \DateTime($j['date']);
            if ($d < $semis || $d > (clone $semis)->modify('+20 days')) {
                continue;
            }
            if ($j['tmin'] < $ref['tGel']) {
                $f *= 0.93;
                $notes[] = sprintf('gel prévu le %s (%.1f °C)', $d->format('d/m'), $j['tmin']);
            }
            if ($j['tmax'] > $ref['tChaleur']) {
                $f *= 0.97;
                $notes[] = sprintf('chaleur prévue le %s (%.1f °C)', $d->format('d/m'), $j['tmax']);
            }
            if ($j['pluie'] > 40) {
                $f *= 0.97;
                $notes[] = sprintf('forte pluie prévue le %s (%d mm)', $d->format('d/m'), $j['pluie']);
            }
        }
        return [max(0.7, $f), $notes];
    }

    /**
     * Vigueur du terrain vue par satellite sur les 12 derniers mois :
     * un terrain dont la végétation monte haut produit plus ; une chute récente augmente le risque.
     */
    private function vigueurSatellite(CodeTerrain $t): array
    {
        $max = null;
        $vals = [];
        foreach ($this->observations->findPourTerrain($t, new \DateTime('-365 days')) as $o) {
            if ($o->getNdvi() !== null && ($o->getNuages() ?? 0) <= 60) {
                $vals[] = $o->getNdvi();
                $max = max($max ?? 0, $o->getNdvi());
            }
        }
        if ($max === null || count($vals) < 3) {
            return ['facteur' => 1.0, 'risque' => 1.0, 'ndviMax' => null, 'note' => 'pas de données satellites'];
        }
        $facteur = match (true) {
            $max >= 0.7 => 1.05,
            $max >= 0.5 => 1.0,
            $max >= 0.3 => 0.92,
            default => 0.85,
        };
        $chute = max(array_slice($vals, -5)) - end($vals);
        $risque = $chute > 0.15 ? 0.92 : 1.0;
        return ['facteur' => $facteur, 'risque' => $risque, 'ndviMax' => round($max, 2), 'note' => sprintf('NDVI maximum %.2f sur 12 mois%s', $max, $risque < 1 ? ', baisse récente' : '')];
    }

    private function derniereImage(CodeTerrain $t): ?\App\Entity\ObservationSatellite
    {
        $obs = array_reverse($this->observations->findPourTerrain($t, new \DateTime('-60 days')));
        foreach ($obs as $o) {
            if (($o->getImageUrl() || $o->getNdvi() !== null) && ($o->getNuages() ?? 0) <= 60) {
                return $o;
            }
        }
        return null;
    }

    /** @return array<string, float> date => NDVI moyen (campagne en cours) */
    private function serieNdvi(CodeTerrain $t): array
    {
        $out = [];
        foreach ($this->observations->findPourTerrain($t, ProfilsCultures::debutCampagne(new \DateTime())) as $o) {
            if ($o->getNdvi() !== null && ($o->getNuages() ?? 0) <= 60) {
                $out[$o->getDate()->format('Y-m-d')] = round($o->getNdvi(), 3);
            }
        }
        return $out;
    }
}

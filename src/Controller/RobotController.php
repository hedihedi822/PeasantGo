<?php

namespace App\Controller;

use App\Entity\CodeTerrain;
use App\Meteo\MeteoService;
use App\Repository\AnalyseIARepository;
use App\Repository\EstimationTerrainRepository;
use App\Repository\ZoneRepository;
use App\Robot\BilanZones;
use App\Robot\Localisation;
use App\Robot\Referentiel;
use App\Robot\RobotService;
use App\Terrain\TerrainActif;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/** Résultats du robot PeasantGo.AI pour le terrain actif. */
final class RobotController extends AbstractController
{
    use JsonApiTrait;

    public function __construct(private TerrainActif $terrainActif)
    {
    }

    /** Tableau, conseil IA, analyse satellite et météo du terrain actif. */
    #[Route('/api/robot/terrain', name: 'api_robot_terrain', methods: ['GET'])]
    public function terrain(EstimationTerrainRepository $estimations, AnalyseIARepository $analyses, MeteoService $meteo, RobotService $robot): JsonResponse
    {
        $t = $this->terrainActif->get();
        if (!$t) {
            return $this->ok(['terrain' => null]);
        }
        $lignes = $estimations->dernierLot($t);

        // Premier passage, ou plan modifié depuis le dernier calcul : on recalcule tout de suite (sans IA pour répondre vite)
        $erreurs = [];
        $dernierChangement = null;
        foreach ($t->getCultivations() as $c) {
            $dernierChangement = max($dernierChangement, $c->getCreatedAt());
        }
        if (!$lignes || ($dernierChangement && $dernierChangement > $lignes[0]->getCreatedAt())) {
            try {
                $r = $robot->analyserTerrain($t, false);
                $lignes = $r['estimations'];
                $erreurs = $r['erreurs'];
            } catch (\Throwable $e) {
                $erreurs[] = $e->getMessage();
            }
        }

        $prevision = [];
        if ($p = $t->getPoint()) {
            try {
                $prevision = array_slice($meteo->prevision($p[0], $p[1]), 0, 7);
            } catch (\Throwable $e) {
                $erreurs[] = 'Météo indisponible : ' . $e->getMessage();
            }
        }

        return $this->ok([
            'terrain' => [
                'id' => $t->getCodeTerrain(), 'nom' => $t->getNom(), 'surface' => $t->getSurface(),
                'localite' => $t->getLocalite()?->getNom(), 'gouvernorat' => $t->getGouvernorat()?->getNom(),
                'irrigue' => $t->isIrrigue(), 'contour' => (bool) $t->getGeometrie(),
            ],
            'lignes' => $tableau = array_map(fn ($e) => $e->toArray(), $lignes),
            'prevues' => self::lignesPrevues($t, $tableau),
            'calculeLe' => $lignes ? $lignes[0]->getCreatedAt()->format('Y-m-d H:i') : null,
            'conseil' => $analyses->derniere($t, 'conseil')?->toArray(),
            'satellite' => $analyses->derniere($t, 'satellite')?->toArray(),
            'meteo' => $prevision,
            'meteoDemo' => $meteo->estDemo(),
            'erreurs' => $erreurs,
        ]);
    }

    /** Bouton « Actualiser l'analyse » (au plus une fois toutes les 5 minutes). */
    #[Route('/api/robot/terrain/analyser', name: 'api_robot_analyser', methods: ['POST'])]
    public function analyser(Request $request, RobotService $robot, AnalyseIARepository $analyses, Localisation $localisation): JsonResponse
    {
        $this->assertSameOrigin($request);
        $t = $this->terrainActif->get();
        if (!$t) {
            return $this->fail('Choisissez d\'abord un terrain.');
        }
        // limite sur la dernière analyse complète (avec conseil), pas sur le calcul rapide de la première visite
        $dernier = $analyses->derniere($t, 'conseil');
        if ($dernier && $dernier->getCreatedAt() > new \DateTime('-5 minutes')) {
            return $this->fail('Analyse déjà faite il y a moins de 5 minutes.', 429);
        }
        set_time_limit(300);
        $localisation->localiser($t, true);
        try {
            $r = $robot->analyserTerrain($t, true);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
        return $this->ok(['message' => sprintf('%d cultures analysées.', count($r['estimations'])), 'erreurs' => $r['erreurs']]);
    }

    /**
     * Accueil visiteur : le tableau du robot pour un terrain type de 1 ha dans le gouvernorat du visiteur
     * (position du navigateur, ou gouvernorat choisi), limité aux productions connues de cette région.
     * Une partie des colonnes est réservée aux membres.
     */
    #[Route('/api/robot/visiteur', name: 'api_robot_visiteur', methods: ['GET'])]
    public function visiteur(Request $request, RobotService $robot, ZoneRepository $zones): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lon = $request->query->get('lon');
        $z = null;
        if (is_numeric($lat) && is_numeric($lon)) {
            $z = $zones->plusProche('gouvernorat', (float) $lat, (float) $lon);
        } elseif ($code = $request->query->get('gouvernorat')) {
            $z = $zones->findOneByCode('gouvernorat', (string) $code);
        }
        $z ??= $zones->findOneByCode('gouvernorat', 'TN-TUNIS') ?? ($zones->parNiveau('gouvernorat')[0] ?? null);
        if (!$z) {
            return $this->fail('Zones non initialisées : php bin/console app:robot:initialiser', 503);
        }
        $nomNorm = ZoneRepository::normaliser($z->getNom());
        $connues = array_keys(array_filter(Referentiel::ZONES_PRODUCTION, fn ($g) => in_array($nomNorm, array_map([ZoneRepository::class, 'normaliser'], $g), true)));
        $lignes = $robot->estimerZone($z);
        $connuesIci = $connues ? array_values(array_filter($lignes, fn ($l) => in_array($l['cle'], $connues, true))) : [];
        $membre = $this->getUser() !== null;

        return $this->ok([
            'zone' => ['code' => $z->getCode(), 'nom' => $z->getNom()],
            'specialites' => (bool) $connuesIci,
            'lignes' => array_map(fn ($l) => $membre ? $l : self::masquer($l), $connuesIci ?: $lignes),
            'gouvernorats' => array_map(fn ($g) => ['code' => $g->getCode(), 'nom' => $g->getNom()], $zones->parNiveau('gouvernorat')),
        ]);
    }

    /** Colonnes réservées aux membres : réussite, production, prix, bénéfice et détail du calcul. */
    private static function masquer(array $l): array
    {
        $d = $l['details'];
        return array_merge($l, [
            'tauxReussite' => null, 'productionEstimee' => null, 'prix' => null, 'benefice' => null, 'recommande' => false,
            'details' => array_intersect_key($d, array_flip(['famille', 'type', 'unite', 'taille', 'affichage', 'surface'])),
            'verrou' => true,
        ]);
    }

    /**
     * Cultures et élevages prévus par l'agriculteur (fenêtre d'accueil, partie 1, ou cultures en cours),
     * avec les estimations du robot ramenées à la surface ou à l'effectif prévu.
     */
    private static function lignesPrevues(\App\Entity\CodeTerrain $t, array $tableau): array
    {
        $parCle = array_column($tableau, null, 'cle');
        $prevu = [];
        foreach ($t->getCultivations() as $c) {
            if (!$c->isActif() || $c->getStatut() === 'termine' || $c->getOrigine() === 'historique') {
                continue;
            }
            $cle = \App\Satellite\ProfilsCultures::depuisDeclaration($c->getCultureCle(), $c->getCultureNom()) ?? $c->getCultureCle();
            if (!isset($parCle[$cle])) {
                continue;
            }
            $prevu[$cle]['surface'] = ($prevu[$cle]['surface'] ?? 0) + $c->getSurface();
            $prevu[$cle]['effectif'] = ($prevu[$cle]['effectif'] ?? 0) + (int) $c->getEffectif();
        }
        $out = [];
        foreach ($prevu as $cle => $p) {
            $l = $parCle[$cle];
            $animal = ($l['details']['famille'] ?? '') === 'animal';
            $base = $animal ? (float) ($l['details']['taille'] ?? 1) : max(0.01, $t->getSurface());
            $f = ($animal ? $p['effectif'] : $p['surface']) / $base;
            foreach (['productionEstimee', 'cout', 'benefice'] as $k) {
                $l[$k] = round($l[$k] * $f, 2);
            }
            $l['prevue'] = $animal ? ['effectif' => $p['effectif']] : ['surface' => round($p['surface'], 2)];
            if ($animal) {
                $l['details']['taille'] = $p['effectif'];
            } else {
                $l['details']['surface'] = round($p['surface'], 2);
            }
            $out[] = $l;
        }
        usort($out, fn ($a, $b) => $b['benefice'] <=> $a['benefice']);
        return $out;
    }

    /** Classement des cultures et élevages les plus rentables (monde, Tunisie, gouvernorat, localité). */
    #[Route('/robot/classement', name: 'app_robot_classement', methods: ['GET'])]
    public function classement(ZoneRepository $zones): Response
    {
        $t = $this->getUser() ? $this->terrainActif->get() : null;
        return $this->render('robot/classement.html.twig', [
            'terrain' => $t,
            'gouvernorats' => $zones->parNiveau('gouvernorat'),
            'gouvernoratDefaut' => $t?->getGouvernorat()?->getCode(),
        ]);
    }

    #[Route('/api/robot/classement/{niveau}', name: 'api_robot_classement', methods: ['GET'], requirements: ['niveau' => 'monde|pays|gouvernorat|localite'])]
    public function classementApi(string $niveau, Request $request, \App\Robot\Classement $classement, ZoneRepository $zones): JsonResponse
    {
        set_time_limit(300);
        $membre = $this->getUser() !== null;
        $t = $membre ? $this->terrainActif->get() : null;
        $zone = null;
        switch ($niveau) {
            case 'monde':
                return $this->ok(['niveau' => $niveau, 'zone' => 'Monde (pays suivis)', 'lignes' => $classement->monde()]);
            case 'pays':
                $lignes = $classement->pays();
                $zone = 'Tunisie';
                break;
            case 'gouvernorat':
                $g = $request->query->get('gouvernorat') ? $zones->findOneByCode('gouvernorat', (string) $request->query->get('gouvernorat')) : $t?->getGouvernorat();
                $g ??= $zones->findOneByCode('gouvernorat', 'TN-TUNIS');
                if (!$g) {
                    return $this->fail('Gouvernorat inconnu.', 404);
                }
                $lignes = $classement->zone($g);
                $zone = $g->getNom();
                break;
            default:
                if (!$t) {
                    return $this->ok(['niveau' => $niveau, 'zone' => null, 'lignes' => [], 'message' => $membre ? 'Entrez avec un terrain pour voir le classement de votre localité.' : 'Connectez-vous et entrez avec votre terrain pour voir le classement de votre localité.']);
                }
                $lignes = $classement->terrain($t);
                $zone = ($t->getLocalite()?->getNom() ?? $t->getNom()) . ' (votre terrain « ' . $t->getNom() . ' »)';
        }
        if (!$membre) {
            $lignes = array_map(fn ($l) => array_merge($l, ['benefice' => null, 'tauxReussite' => null, 'prix' => null, 'verrou' => true]), $lignes);
        }
        return $this->ok(['niveau' => $niveau, 'zone' => $zone, 'lignes' => $lignes]);
    }

    /** Offre et demande d'une culture par zone (monde, Tunisie, gouvernorat) : réservé à l'équipe (security.yaml). */
    #[Route('/api/robot/zones/{cle}', name: 'api_robot_zones', methods: ['GET'], requirements: ['cle' => '[A-Za-z]+'])]
    public function zonesApi(string $cle, Request $request, BilanZones $bilans, ZoneRepository $zones): JsonResponse
    {
        if (!Referentiel::existe($cle)) {
            throw new NotFoundHttpException();
        }
        $g = $zones->findOneByCode('gouvernorat', (string) $request->query->get('gouvernorat', 'TN-TUNIS'));
        return $this->ok(['culture' => Referentiel::nom($cle), 'lignes' => $bilans->tableau($cle, null, $g)]);
    }

    #[Route('/robot/zones/{cle}', name: 'app_robot_zones', methods: ['GET'], requirements: ['cle' => '[A-Za-z]+'])]
    public function zones(string $cle, Request $request, BilanZones $bilans, ZoneRepository $zones): Response
    {
        if (!Referentiel::existe($cle)) {
            throw $this->createNotFoundException();
        }
        $g = $zones->findOneByCode('gouvernorat', (string) $request->query->get('gouvernorat', 'TN-TUNIS')) ?? ($zones->parNiveau('gouvernorat')[0] ?? null);
        return $this->render('robot/zones.html.twig', [
            'cle' => $cle,
            'culture' => Referentiel::nom($cle),
            'cultures' => Referentiel::noms(),
            'gouvernorats' => $zones->parNiveau('gouvernorat'),
            'gouvernorat' => $g,
            'lignes' => $bilans->tableau($cle, null, $g),
            'tendanceGouv' => $g ? $bilans->tendance($g, $cle) : null,
        ]);
    }
}

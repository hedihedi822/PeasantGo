<?php

namespace App\Controller;

use App\Entity\CodeCompteurs;
use App\Entity\CodeTerrain;
use App\Robot\Localisation;
use App\Terrain\Geocodage;
use App\Terrain\Justificatifs;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use App\Entity\Ferme;
use App\Entity\Machine;
use App\Entity\Utilisateur;
use App\Repository\ObservationSatelliteRepository;
use App\Repository\PredictionCultureRepository;
use App\Satellite\ProfilsCultures;
use App\Satellite\SatelliteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Profil agricole de l'utilisateur connecté : terrains, fermes, compteurs, machines.
 * Utilisé par profil.html.twig et formulaire.html.twig.
 */
#[Route('/api')]
final class TerrainController extends AbstractController
{
    use JsonApiTrait;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public static function terrainToArray(CodeTerrain $t): array
    {
        return [
            'id' => $t->getCodeTerrain(),
            'nom' => $t->getNom(),
            'localisation' => $t->getLocalisation(),
            'codePostal' => $t->getCodePostal(),
            'surface' => $t->getSurface(),
            'surfaceUtilisee' => $t->getSurfaceUtilisee(),
            'enExploitation' => $t->isEnExploitation(),
            'geometrie' => $t->getGeometrie(),
            'contourApproximatif' => $t->isContourApproximatif(),
            'point' => $t->getPoint(),
            'localite' => $t->getLocalite()?->getNom(),
            'gouvernorat' => $t->getGouvernorat()?->getNom(),
            'justificatif' => [
                'type' => $t->getJustificatifType(),
                'statut' => $t->getJustificatifStatut(),
                'label' => $t->getJustificatifStatutLabel(),
                'utilisable' => $t->isUtilisable(),
                'motifs' => $t->getJustificatifVerification()['motifs'] ?? [],
                'decision' => $t->getJustificatifVerification()['equipe']['motif'] ?? null,
            ],
            'satelliteSyncAt' => $t->getSatelliteSyncAt()?->format('Y-m-d H:i'),
            'fermes' => array_map(fn (Ferme $f) => ['id' => $f->getId(), 'localisation' => $f->getLocalisation(), 'type' => $f->getType()], $t->getFermes()->toArray()),
            'compteurs' => array_map(fn (CodeCompteurs $c) => ['id' => $c->getCodeCompteur(), 'numero' => $c->getNumero(), 'type' => $c->getType(), 'enFonctionnement' => $c->isEnFonctionnement()], $t->getCompteurs()->toArray()),
        ];
    }

    #[Route('/terrains', name: 'api_terrains', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->ok([
            'terrains' => array_map([self::class, 'terrainToArray'], $this->user()->getTerrains()->toArray()),
            'machines' => array_map(fn (Machine $m) => ['id' => $m->getId(), 'type' => $m->getType(), 'annee' => $m->getAnnee()], $this->user()->getMachines()->toArray()),
        ]);
    }

    /**
     * Nouveau terrain. JSON (nom, localisation, surface) ou formulaire multipart complet
     * (+ geometrie en JSON, contourApproximatif, justificatifType, fichier « justificatif »).
     * Sans justificatif le terrain est créé mais reste « à fournir » : on ne peut pas encore entrer avec.
     */
    #[Route('/terrains', name: 'api_terrain_add', methods: ['POST'])]
    public function add(Request $request, Justificatifs $justificatifs, Localisation $localisation): JsonResponse
    {
        $this->assertSameOrigin($request);
        $multipart = str_starts_with((string) $request->headers->get('Content-Type'), 'multipart/form-data');
        $d = $multipart ? $request->request->all() : $this->payload($request);
        $nom = self::str($d, 'nom', 120);
        $localisationTxt = self::str($d, 'localisation');
        $surface = (float) str_replace(',', '.', (string) ($d['surface'] ?? 0));
        $codePostal = self::str($d, 'codePostal', 10);

        if ($nom === '' && $localisationTxt === '') {
            return $this->fail('Le nom ou la localisation du terrain est obligatoire.');
        }
        if ($surface <= 0 || $surface > 100000) {
            return $this->fail('La surface doit être un nombre positif (en hectares).');
        }
        if ($codePostal !== '' && !ctype_digit($codePostal)) {
            return $this->fail('Le code postal doit être numérique.');
        }

        $t = (new CodeTerrain())
            ->setProprietaire($this->user())
            ->setNom($nom !== '' ? $nom : $localisationTxt)
            ->setLocalisation($localisationTxt !== '' ? $localisationTxt : null)
            ->setSurface(round($surface, 2))
            ->setCodePostal($codePostal !== '' ? (int) $codePostal : null);

        $avertissement = null;
        if (!empty($d['geometrie'])) {
            $g = is_string($d['geometrie']) ? json_decode($d['geometrie'], true) : $d['geometrie'];
            [$geo, $surfaceCarte, $erreur] = self::validerGeometrie(is_array($g) ? $g : []);
            if ($erreur) {
                return $this->fail($erreur);
            }
            $t->setGeometrie($geo)->setContourApproximatif(!empty($d['contourApproximatif']) && $d['contourApproximatif'] !== '0' && $d['contourApproximatif'] !== 'false');
            $avertissement = self::ecartSurface($surfaceCarte, $t->getSurface());
        }
        if ($multipart) {
            try {
                $justificatifs->enregistrer($t, self::str($d, 'justificatifType', 20), $request->files->get('justificatif'));
            } catch (\InvalidArgumentException $e) {
                return $this->fail($e->getMessage());
            }
        }
        if (!empty($d['irrigue']) && $d['irrigue'] !== '0' && $d['irrigue'] !== 'false') {
            // eau disponible : le robot compte la culture comme irriguée (le détail se complète dans le profil agricole)
            $c = (new CodeCompteurs())->setTerrain($t)->setNumero('déclaré')->setType('Irrigation');
            $t->getCompteurs()->add($c);
            $this->em->persist($c);
        }
        $this->em->persist($t);
        $this->em->flush();
        try {
            $localisation->localiser($t, true);
        } catch (\Throwable) {
            // la localité sera retrouvée à la première analyse
        }
        if ($t->getJustificatifFichier()) {
            $justificatifs->verifier($t);
        }

        return $this->ok(['terrain' => self::terrainToArray($t), 'avertissement' => $avertissement], 201);
    }

    /** Le robot trouve le terrain à partir de l'adresse et propose un contour de la surface déclarée. */
    #[Route('/terrains/localiser', name: 'api_terrain_localiser', methods: ['POST'])]
    public function localiser(Request $request, Geocodage $geocodage): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        $adresse = self::str($d, 'adresse', 300);
        $surface = (float) str_replace(',', '.', (string) ($d['surface'] ?? 1));
        if (mb_strlen($adresse) < 3) {
            return $this->fail('Écrivez l\'adresse du terrain (lieu-dit, délégation, gouvernorat).');
        }
        $r = $geocodage->localiser($adresse);
        if (!$r) {
            return $this->fail('Adresse introuvable : ajoutez la délégation et le gouvernorat, ou dessinez le terrain sur la carte.', 404);
        }
        return $this->ok($r + [
            'geometrie' => Geocodage::carre($r['lat'], $r['lon'], $surface > 0 ? min($surface, 5000) : 1),
            'message' => match ($r['precision']) {
                'adresse' => 'Adresse trouvée. Ajustez les coins du contour sur la carte si besoin.',
                'localite' => sprintf('Localité trouvée (%s) : le contour est placé au centre, déplacez-le sur votre terrain.', $r['nom']),
                default => sprintf('Seul le gouvernorat a été reconnu (%s) : zoomez et dessinez votre terrain.', $r['nom']),
            },
        ]);
    }

    /** Nouveau justificatif pour un terrain existant (à fournir, refusé, ou mise à jour). */
    #[Route('/terrains/{id}/justificatif', name: 'api_terrain_justificatif', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function justificatif(int $id, Request $request, Justificatifs $justificatifs): JsonResponse
    {
        $this->assertSameOrigin($request);
        $t = $this->terrain($id);
        try {
            $justificatifs->enregistrer($t, (string) $request->request->get('justificatifType', ''), $request->files->get('justificatif'));
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }
        $this->em->flush();
        $justificatifs->verifier($t);
        return $this->ok(['terrain' => self::terrainToArray($t), 'message' => 'Justificatif reçu : ' . mb_strtolower($t->getJustificatifStatutLabel()) . '.']);
    }

    /** Le document lui-même : visible par son propriétaire et par l'équipe seulement. */
    #[Route('/terrains/{id}/justificatif', name: 'api_terrain_justificatif_voir', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function voirJustificatif(int $id, Justificatifs $justificatifs): Response
    {
        $t = $this->em->find(CodeTerrain::class, $id);
        if (!$t || ($t->getProprietaire() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN'))) {
            throw $this->createNotFoundException();
        }
        $chemin = $justificatifs->chemin($t);
        if (!$chemin) {
            throw $this->createNotFoundException();
        }
        $r = new BinaryFileResponse($chemin);
        $r->headers->set('Content-Type', $t->getJustificatifMime());
        $r->headers->set('X-Content-Type-Options', 'nosniff');
        $r->headers->set('Cache-Control', 'private, no-store');
        $r->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'justificatif-' . $t->getCodeTerrain() . '.' . pathinfo($chemin, PATHINFO_EXTENSION));
        return $r;
    }

    #[Route('/terrains/{id}', name: 'api_terrain_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $t = $this->terrain($id);
        $this->user()->getTerrains()->removeElement($t);
        $this->em->remove($t);
        $this->em->flush();
        return $this->ok();
    }

    #[Route('/terrains/{id}/fermes', name: 'api_ferme_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addFerme(int $id, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $t = $this->terrain($id);
        $d = $this->payload($request);
        $loc = self::str($d, 'localisation');
        $type = self::str($d, 'type', 120);
        if ($loc === '' || $type === '') {
            return $this->fail('Localisation et type sont obligatoires.');
        }
        $f = (new Ferme())->setTerrain($t)->setLocalisation($loc)->setType($type);
        $t->getFermes()->add($f);
        $this->em->persist($f);
        $this->em->flush();
        return $this->ok(['terrain' => self::terrainToArray($t)], 201);
    }

    #[Route('/terrains/{id}/compteurs', name: 'api_compteur_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addCompteur(int $id, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $t = $this->terrain($id);
        $d = $this->payload($request);
        $num = self::str($d, 'numero', 60);
        $type = self::str($d, 'type', 60);
        if ($num === '' || $type === '') {
            return $this->fail('Type et numéro sont obligatoires.');
        }
        $c = (new CodeCompteurs())->setTerrain($t)->setNumero($num)->setType($type);
        $t->getCompteurs()->add($c);
        $this->em->persist($c);
        $this->em->flush();
        return $this->ok(['terrain' => self::terrainToArray($t)], 201);
    }

    #[Route('/fermes/{id}', name: 'api_ferme_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteFerme(int $id, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $f = $this->em->find(Ferme::class, $id);
        if (!$f || $f->getTerrain()->getProprietaire() !== $this->user()) {
            throw new NotFoundHttpException();
        }
        $t = $f->getTerrain();
        $t->getFermes()->removeElement($f);
        $this->em->remove($f);
        $this->em->flush();
        return $this->ok(['terrain' => self::terrainToArray($t)]);
    }

    #[Route('/compteurs/{id}', name: 'api_compteur_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteCompteur(int $id, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $c = $this->em->find(CodeCompteurs::class, $id);
        if (!$c || $c->getTerrain()->getProprietaire() !== $this->user()) {
            throw new NotFoundHttpException();
        }
        $t = $c->getTerrain();
        $t->getCompteurs()->removeElement($c);
        $this->em->remove($c);
        $this->em->flush();
        return $this->ok(['terrain' => self::terrainToArray($t)]);
    }

    #[Route('/machines', name: 'api_machine_add', methods: ['POST'])]
    public function addMachine(Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        $type = self::str($d, 'type', 120);
        $annee = (int) ($d['annee'] ?? 0);
        $max = (int) date('Y') + 1;
        if ($type === '') {
            return $this->fail('Le type de machine est obligatoire.');
        }
        if ($annee < 1900 || $annee > $max) {
            return $this->fail("L'année doit être comprise entre 1900 et $max.");
        }
        $m = (new Machine())->setProprietaire($this->user())->setType($type)->setAnnee($annee);
        $this->user()->getMachines()->add($m);
        $this->em->persist($m);
        $this->em->flush();
        return $this->ok(['machine' => ['id' => $m->getId(), 'type' => $m->getType(), 'annee' => $m->getAnnee()]], 201);
    }

    #[Route('/machines/{id}', name: 'api_machine_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteMachine(int $id, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $m = $this->em->find(Machine::class, $id);
        if (!$m || $m->getProprietaire() !== $this->user()) {
            throw new NotFoundHttpException();
        }
        $this->user()->getMachines()->removeElement($m);
        $this->em->remove($m);
        $this->em->flush();
        return $this->ok();
    }

    /**
     * Contour GPS dessiné sur la carte (GeoJSON Polygon, coordonnées [lon, lat]).
     * Corps : { geometrie: {type: "Polygon", coordinates: [[[lon, lat], ...]]} } ou { geometrie: null } pour effacer.
     */
    #[Route('/terrains/{id}/geometrie', name: 'api_terrain_geometrie', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function geometrie(int $id, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $t = $this->terrain($id);
        $g = $this->payload($request)['geometrie'] ?? null;

        if ($g === null) {
            $t->setGeometrie(null);
            $this->em->flush();
            return $this->ok(['terrain' => self::terrainToArray($t)]);
        }

        [$geo, $surfaceHa, $erreur] = self::validerGeometrie($g);
        if ($erreur) {
            return $this->fail($erreur);
        }
        $t->setGeometrie($geo)->setContourApproximatif(false);
        $this->em->flush();

        return $this->ok([
            'terrain' => self::terrainToArray($t),
            'surfaceCarte' => round($surfaceHa, 2),
            'avertissement' => self::ecartSurface($surfaceHa, $t->getSurface()),
        ]);
    }

    /** Données satellites + reconnaissance de culture d'un terrain. */
    #[Route('/terrains/{id}/satellite', name: 'api_terrain_satellite', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function satellite(int $id, ObservationSatelliteRepository $obs, PredictionCultureRepository $pred, SatelliteService $satellite): JsonResponse
    {
        $t = $this->terrain($id);
        $p = $pred->findDerniere($t);
        $debut = ProfilsCultures::debutCampagne(new \DateTime());
        $courbes = [];
        if ($p) {
            foreach (array_keys(array_slice($p->getProbabilites(), 0, 3, true)) as $cle) {
                for ($j = 0; $j <= 364; $j += 10) {
                    $courbes[$cle][] = [$debut->modify("+$j days")->format('Y-m-d'), round(ProfilsCultures::ndvi($cle, $j), 3)];
                }
            }
        }

        return $this->ok([
            'sources' => $satellite->sources(),
            'observations' => array_map(fn ($o) => $o->toArray(), $obs->findPourTerrain($t, new \DateTime('-365 days'))),
            'prediction' => $p?->toArray(),
            'noms' => array_map(fn ($x) => $x[0], ProfilsCultures::PROFILS),
            'profils' => $courbes,
            'campagne' => $debut->format('Y-m-d'),
            'synchro' => $t->getSatelliteSyncAt()?->format('Y-m-d H:i'),
        ]);
    }

    /** Bouton « Actualiser » : interroge toutes les sources (limité à une fois toutes les 10 minutes). */
    #[Route('/terrains/{id}/satellite', name: 'api_terrain_satellite_sync', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function satelliteSync(int $id, Request $request, SatelliteService $satellite): JsonResponse
    {
        $this->assertSameOrigin($request);
        $t = $this->terrain($id);
        if (!$t->getGeometrie()) {
            return $this->fail('Dessinez d\'abord le contour du terrain sur la carte.');
        }
        if (!array_filter($satellite->sources())) {
            return $this->fail('Aucune source satellite n\'est configurée sur le serveur (voir README).', 503);
        }
        if ($t->getSatelliteSyncAt() && $t->getSatelliteSyncAt() > new \DateTime('-10 minutes')) {
            return $this->fail('Données déjà actualisées il y a moins de 10 minutes.', 429);
        }
        set_time_limit(180);
        $r = $satellite->synchroniser($t);
        return $this->ok([
            'nouvelles' => $r['nouvelles'],
            'sources' => $r['sources'],
            'erreurs' => (object) $r['erreurs'],
            'message' => sprintf('%d nouvelle(s) image(s) via %s.', $r['nouvelles'], $r['sources'] ? implode(', ', $r['sources']) : 'aucune source'),
        ]);
    }

    /** Surface géodésique approximative d'un anneau [lon, lat] (formule sphérique, en hectares). */
    /**
     * Contrôle un contour GeoJSON Polygon ([lon, lat]).
     * @return array{0: ?array, 1: float, 2: ?string} [géométrie propre, surface en ha, erreur]
     */
    public static function validerGeometrie(array $g): array
    {
        $ring = $g['coordinates'][0] ?? null;
        if (($g['type'] ?? null) !== 'Polygon' || !is_array($ring) || count($ring) < 4 || count($ring) > 500) {
            return [null, 0, 'Contour invalide : dessinez un polygone de 3 à 500 points.'];
        }
        $propre = [];
        foreach ($ring as $pt) {
            if (!is_array($pt) || count($pt) < 2 || !is_numeric($pt[0]) || !is_numeric($pt[1])
                || $pt[0] < -180 || $pt[0] > 180 || $pt[1] < -90 || $pt[1] > 90) {
                return [null, 0, 'Coordonnées invalides dans le contour.'];
            }
            $propre[] = [round((float) $pt[0], 7), round((float) $pt[1], 7)];
        }
        if ($propre[0] !== end($propre)) {
            $propre[] = $propre[0]; // ferme l'anneau
        }
        $surfaceHa = self::surfaceHectares($propre);
        if ($surfaceHa < 0.01) {
            return [null, 0, 'Contour trop petit.'];
        }
        if ($surfaceHa > 5000) {
            return [null, 0, sprintf('Contour trop grand (%.0f ha). Dessinez une seule parcelle.', $surfaceHa)];
        }
        return [['type' => 'Polygon', 'coordinates' => [$propre]], $surfaceHa, null];
    }

    private static function ecartSurface(float $surfaceCarte, float $declaree): ?string
    {
        $ecart = $declaree > 0 ? abs($surfaceCarte - $declaree) / $declaree : 0;
        return $ecart > 0.25 ? sprintf('Le contour mesure %.2f ha alors que le terrain est déclaré à %.2f ha.', $surfaceCarte, $declaree) : null;
    }

    public static function surfaceHectares(array $ring): float
    {
        $r = 6378137.0;
        $total = 0.0;
        $n = count($ring);
        for ($i = 0; $i < $n - 1; ++$i) {
            [$lon1, $lat1] = $ring[$i];
            [$lon2, $lat2] = $ring[$i + 1];
            $total += deg2rad($lon2 - $lon1) * (2 + sin(deg2rad($lat1)) + sin(deg2rad($lat2)));
        }
        return abs($total * $r * $r / 2) / 10000;
    }

    private function user(): Utilisateur
    {
        /** @var Utilisateur $u */
        $u = $this->getUser();
        return $u;
    }

    private function terrain(int $id): CodeTerrain
    {
        $t = $this->em->find(CodeTerrain::class, $id);
        if (!$t || $t->getProprietaire() !== $this->user()) {
            throw new NotFoundHttpException('Terrain introuvable');
        }
        return $t;
    }
}

<?php

namespace App\Controller;

use App\Entity\CodeCompteurs;
use App\Entity\CodeTerrain;
use App\Entity\Consommation;
use App\Entity\Cout;
use App\Entity\Cultivation;
use App\Entity\DateCultivation;
use App\Entity\DemandePartenariat;
use App\Entity\EtatProduction;
use App\Entity\Exportation;
use App\Entity\Formation;
use App\Entity\MessageContact;
use App\Entity\AnalyseIA;
use App\Entity\EstimationTerrain;
use App\Entity\ModeleCalibration;
use App\Entity\ObservationSatellite;
use App\Entity\PrixIntrant;
use App\Entity\PrixMarche;
use App\Entity\SourceVeille;
use App\Entity\Zone;
use App\Entity\ZoneStat;
use App\Entity\PredictionCulture;
use App\Entity\Outils;
use App\Entity\Prix;
use App\Entity\Production;
use App\Entity\ProduitsAgricole;
use App\Entity\Region;
use App\Entity\Saison;
use App\Entity\TauxReussite;
use App\Entity\TerreDesignee;
use App\Entity\Utilisateur;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Panneau d'administration (ROLE_ADMIN, voir security.yaml).
 *
 * Toutes les tables sont gérées par un CRUD générique basé sur les métadonnées Doctrine :
 *   GET    /api/admin/{table}          → colonnes + lignes (recherche ?q=, pagination ?page=)
 *   POST   /api/admin/{table}          → création
 *   PUT    /api/admin/{table}/{id}     → modification (id composite : "idP-idS")
 *   DELETE /api/admin/{table}/{id}     → suppression
 *   POST   /api/admin/upload           → envoi d'un fichier (vidéo / image / pdf / word) pour les formations
 */
final class AdminController extends AbstractController
{
    use JsonApiTrait;

    private const PAGE_SIZE = 50;

    /** onglet => [classe, libellé, création autorisée] */
    private const TABLES = [
        'produits' => [ProduitsAgricole::class, 'Produits Agricoles', true],
        'utilisateurs' => [Utilisateur::class, 'Utilisateurs', false],
        'terrains' => [CodeTerrain::class, 'Terrains', true],
        'compteurs' => [CodeCompteurs::class, 'Compteurs', true],
        'cultures' => [Cultivation::class, 'Cultures', true],
        'saisons' => [Saison::class, 'Saisons', true],
        'productions' => [Production::class, 'Productions', true],
        'dates' => [DateCultivation::class, 'Dates de cultivation', true],
        'regions' => [Region::class, 'Régions', true],
        'consommation' => [Consommation::class, 'Consommation', true],
        'cout' => [Cout::class, 'Coûts', true],
        'exportation' => [Exportation::class, 'Exportation', true],
        'etat' => [EtatProduction::class, 'État de production', true],
        'prix' => [Prix::class, 'Prix', true],
        'taux' => [TauxReussite::class, 'Taux de Réussite', true],
        'terre' => [TerreDesignee::class, 'Terre Désignée', true],
        'formation' => [Formation::class, 'Formations', true],
        'outils' => [Outils::class, 'Outils', true],
        'partenariats' => [DemandePartenariat::class, 'Partenariats', false],
        'formateurs' => [\App\Entity\DemandeFormateur::class, 'Candidatures formateurs', false],
        'messages' => [MessageContact::class, 'Messages', false],
        'satellite' => [ObservationSatellite::class, 'Images satellites', false],
        'predictions' => [PredictionCulture::class, 'Cultures reconnues (IA)', false],
        'sources' => [SourceVeille::class, 'Robot : sources de veille', true],
        'zones' => [Zone::class, 'Robot : zones', true],
        'zonestats' => [ZoneStat::class, 'Robot : statistiques par zone', true],
        'prixmarche' => [PrixMarche::class, 'Robot : prix observés', true],
        'prixintrants' => [PrixIntrant::class, 'Robot : prix des intrants', true],
        'estimations' => [EstimationTerrain::class, 'Robot : estimations par terrain', false],
        'analyses' => [AnalyseIA::class, 'Robot : analyses IA', false],
        'calibrations' => [ModeleCalibration::class, 'Robot : apprentissage', false],
        'alertes' => [\App\Entity\AlerteEstimation::class, 'Robot : alertes envoyées', false],
    ];

    /** Champs jamais exposés ni modifiables depuis l'admin. */
    private const HIDDEN = ['motDePasse', 'resetCodeHash', 'resetCodeExpiresAt', 'resetAttempts', 'preferences', 'justificatifFichier', 'justificatifVerification', 'questionnaire'];

    /** Champs en lecture seule. */
    private const READONLY = ['views', 'ratingCount', 'averageRating', 'lastLoginAt', 'avatar', 'createdAt', 'updatedAt'];

    private const CHOICES = [
        ProduitsAgricole::class => ['categorie' => ['Végétale' => 'Végétale', 'Animale' => 'Animale']],
        Cultivation::class => ['statut' => Cultivation::STATUTS],
        MessageContact::class => ['sujet' => MessageContact::SUJETS, 'priorite' => MessageContact::PRIORITES],
        \App\Entity\DemandeFormateur::class => ['statut' => \App\Entity\DemandeFormateur::STATUTS],
        DemandePartenariat::class => ['statut' => ['nouvelle' => 'Nouvelle', 'en_cours' => 'En cours', 'acceptee' => 'Acceptée', 'refusee' => 'Refusée']],
        Formation::class => ['difficulty' => ['debutant' => 'Débutant', 'intermediaire' => 'Intermédiaire', 'avance' => 'Avancé']],
        Zone::class => ['niveau' => Zone::NIVEAUX],
        CodeTerrain::class => ['justificatifStatut' => CodeTerrain::STATUTS_JUSTIFICATIF, 'justificatifType' => CodeTerrain::JUSTIFICATIFS],
        ZoneStat::class => ['source' => ZoneStat::SOURCES],
        PrixMarche::class => ['type' => PrixMarche::TYPES, 'source' => ['veille' => 'Veille', 'recolte' => 'Récolte déclarée', 'admin' => 'Équipe', 'demo' => 'Démo']],
        SourceVeille::class => ['type' => SourceVeille::TYPES],
        PrixIntrant::class => ['source' => ['veille' => 'Veille', 'admin' => 'Équipe', 'demo' => 'Démo']],
    ];

    private const LABELS = [
        'idP' => 'ID Produit', 'idS' => 'ID Saison', 'idO' => 'ID', 'codeTerrain' => 'Code terrain', 'codeCompteur' => 'Code compteur',
        'sousType' => 'Sous-type', 'methodeDeCultivation' => 'Méthode', 'nombreSaisonTotalParAn' => 'Saisons/an',
        'productiviteParHectare' => 'Productivité (t/ha)', 'pourcentageConfianceEstimation' => 'Confiance %',
        'pourcentageConfianceEstimationParEtat' => 'Confiance % (État)', 'pourcentageConfianceEstimationParRegion' => 'Confiance % (Région)',
        'exceProduction' => 'Excès production', 'pourcenatgeExce' => '% excès', 'numeroSaisonParAn' => 'N° saison',
        'formationVideo' => 'Vidéo', 'formationPdf' => 'PDF', 'formationWord' => 'Word', 'posterPath' => 'Affiche',
        'cultureNom' => 'Culture', 'cultureCle' => 'Clé culture', 'dateDebut' => 'Début', 'dateFin' => 'Fin',
        'title' => 'Titre', 'duration' => 'Durée (min)', 'difficulty' => 'Difficulté', 'active' => 'Active',
        'cin' => 'CIN', 'typeCompte' => 'Type', 'aPropos' => 'À propos',
    ];

    public function __construct(private EntityManagerInterface $em, private ValidatorInterface $validator)
    {
    }

    #[Route('/Equipe_PeasantGo', name: 'app_admin', methods: ['GET'])]
    public function index(): Response
    {
        $onglets = [];
        foreach (self::TABLES as $key => [$class, $label]) {
            $onglets[$key] = $label;
        }
        return $this->render('admin/admin.html.twig', ['onglets' => $onglets]);
    }

    #[Route('/api/admin/{table}', name: 'api_admin_list', methods: ['GET'], requirements: ['table' => '[a-z]+'])]
    public function list(string $table, Request $request): JsonResponse
    {
        [$class, $label, $canCreate] = $this->table($table);
        $meta = $this->em->getClassMetadata($class);
        $page = max(1, $request->query->getInt('page', 1));
        $q = trim((string) $request->query->get('q', ''));

        $qb = $this->em->createQueryBuilder()->select('e')->from($class, 'e');
        if ($q !== '') {
            $or = [];
            foreach ($meta->getFieldNames() as $f) {
                if (in_array($f, self::HIDDEN, true)) {
                    continue;
                }
                $type = $meta->getTypeOfField($f);
                if (in_array($type, ['string', 'text'], true)) {
                    $or[] = "LOWER(e.$f) LIKE :q";
                } elseif (in_array($type, ['integer', 'smallint', 'bigint'], true) && ctype_digit($q)) {
                    $or[] = "e.$f = :n";
                }
            }
            if ($or) {
                $qb->andWhere(implode(' OR ', $or))->setParameter('q', '%' . mb_strtolower($q) . '%');
                if (ctype_digit($q) && str_contains(implode(' ', $or), ':n')) {
                    $qb->setParameter('n', (int) $q);
                }
            }
        }
        foreach ($meta->getIdentifierFieldNames() as $idf) {
            $qb->addOrderBy("e.$idf", 'ASC');
        }

        $total = (int) (clone $qb)->select('COUNT(e)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $rows = $qb->setFirstResult(($page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE)->getQuery()->getResult();

        return $this->ok([
            'table' => $table,
            'label' => $label,
            'canCreate' => $canCreate,
            'columns' => $this->columns($meta),
            'rows' => array_map(fn ($e) => $this->serialize($meta, $e), $rows),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
        ]);
    }

    /** Candidatures « Devenir formateur » en attente. */
    #[Route('/api/admin/candidatures-formateurs', name: 'api_admin_formateurs', methods: ['GET'], priority: 10)]
    public function candidaturesFormateurs(): JsonResponse
    {
        $liste = $this->em->getRepository(\App\Entity\DemandeFormateur::class)->findBy(['statut' => ['nouvelle', 'en_cours']], ['createdAt' => 'ASC'], 100);
        return $this->ok(['candidatures' => array_map(fn (\App\Entity\DemandeFormateur $d) => [
            'id' => $d->getId(), 'nom' => $d->getNom(), 'email' => $d->getEmail(), 'telephone' => $d->getTelephone(),
            'profession' => $d->getProfession(), 'experience' => $d->getAnneesExperience(),
            'domaines' => array_map(fn ($k) => \App\Entity\DemandeFormateur::DOMAINES[$k] ?? $k, $d->getDomaines()),
            'formats' => array_map(fn ($k) => \App\Entity\DemandeFormateur::FORMATS[$k] ?? $k, $d->getFormats()),
            'lien' => $d->getLienExemple(), 'message' => $d->getMessage(),
            'compte' => $d->getUtilisateur() !== null, 'recu' => $d->getCreatedAt()->format('Y-m-d H:i'),
        ], $liste)]);
    }

    /** Accepter : crée le compte formateur (ou donne le rôle à un compte existant). Refuser : avec un motif. */
    #[Route('/api/admin/candidatures-formateurs/{id}/{decision}', name: 'api_admin_formateur_decision', methods: ['POST'], requirements: ['id' => '\d+', 'decision' => 'accepter|refuser'], priority: 10)]
    public function decisionFormateur(int $id, string $decision, Request $request, \App\Service\NotificationService $notifications): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->em->find(\App\Entity\DemandeFormateur::class, $id);
        if (!$d || !in_array($d->getStatut(), ['nouvelle', 'en_cours'], true)) {
            throw $this->createNotFoundException();
        }
        $repo = $this->em->getRepository(Utilisateur::class);
        if ($decision === 'refuser') {
            $d->setStatut('refusee')->setMotDePasse(null);
            if ($u = $d->getUtilisateur() ?? $repo->findOneBy(['email' => $d->getEmail()])) {
                $motif = trim(mb_substr((string) ($this->payload($request)['motif'] ?? ''), 0, 500));
                $notifications->envoyerA($u, 'compte', 'Candidature de formateur', 'Votre candidature n\'a pas été retenue pour le moment' . ($motif ? ' : ' . $motif : '.'), '/devenir-formateur');
            }
            $this->em->flush();
            return $this->ok(['message' => 'Candidature refusée.']);
        }

        $u = $d->getUtilisateur() ?? $repo->findOneBy(['email' => $d->getEmail()]);
        $cree = false;
        if (!$u) {
            if (!$d->getMotDePasse()) {
                return $this->fail('Ce candidat n\'a pas de mot de passe enregistré : demandez-lui de recréer sa candidature.');
            }
            $tel = preg_replace('/\s+/', ' ', trim((string) $d->getTelephone()));
            if ($tel === '' || $repo->findOneBy(['telephone' => $tel])) {
                return $this->fail('Le numéro de téléphone du candidat est déjà utilisé par un autre compte.');
            }
            $parts = preg_split('/\s+/', trim($d->getNom()), 2);
            $u = (new \App\Entity\RegularUtilisateur())->setEmail($d->getEmail())->setTelephone($tel)
                ->setPrenom($parts[0])->setNom($parts[1] ?? null);
            $u->setMotDePasse($d->getMotDePasse());
            $this->em->persist($u);
            $cree = true;
        }
        $u->setFormateur(true);
        $d->setUtilisateur($u)->setStatut('acceptee')->setMotDePasse(null);
        $this->em->flush();
        $notifications->envoyerA($u, 'compte', 'Bienvenue parmi les formateurs !', 'Votre candidature est acceptée. Dans votre espace formateur, ajoutez vos vidéos et vos documents PDF : ils apparaissent tout de suite dans la page Formations.', '/espace-formateur');
        $this->em->flush();
        return $this->ok(['message' => $cree ? 'Candidature acceptée : le compte formateur est créé (identifiant : ' . $d->getEmail() . ', avec le mot de passe choisi par le candidat).' : 'Candidature acceptée : le compte existant devient formateur.']);
    }

    /** Justificatifs de terrains à vérifier par l'équipe (en attente ou pré-vérifiés par le robot). */
    #[Route('/api/admin/justificatifs', name: 'api_admin_justificatifs', methods: ['GET'], priority: 10)]
    public function justificatifs(): JsonResponse
    {
        $terrains = $this->em->createQuery('SELECT t, u FROM ' . CodeTerrain::class . " t JOIN t.proprietaire u WHERE t.justificatifStatut IN ('en_attente', 'verifie_ia') ORDER BY t.justificatifLe ASC")->setMaxResults(100)->getResult();
        return $this->ok(['terrains' => array_map(fn (CodeTerrain $t) => [
            'id' => $t->getCodeTerrain(),
            'nom' => $t->getNom(),
            'surface' => $t->getSurface(),
            'localisation' => $t->getLocalisation(),
            'gouvernorat' => $t->getGouvernorat()?->getNom(),
            'agriculteur' => $t->getProprietaire()->getNomAffiche(),
            'cin' => $t->getProprietaire()->getCin(),
            'email' => $t->getProprietaire()->getEmail(),
            'type' => CodeTerrain::JUSTIFICATIFS[$t->getJustificatifType()] ?? $t->getJustificatifType(),
            'statut' => $t->getJustificatifStatut(),
            'statutLabel' => $t->getJustificatifStatutLabel(),
            'recu' => $t->getJustificatifLe()?->format('Y-m-d H:i'),
            'lu' => $t->getJustificatifVerification()['lu'] ?? null,
            'motifs' => $t->getJustificatifVerification()['motifs'] ?? [],
            'document' => $this->generateUrl('api_terrain_justificatif_voir', ['id' => $t->getCodeTerrain()]),
        ], $terrains)]);
    }

    #[Route('/api/admin/justificatifs/{id}/{decision}', name: 'api_admin_justificatif_decision', methods: ['POST'], requirements: ['id' => '\d+', 'decision' => 'valider|refuser'], priority: 10)]
    public function decisionJustificatif(int $id, string $decision, Request $request, \App\Terrain\Justificatifs $justificatifs): JsonResponse
    {
        $this->assertSameOrigin($request);
        $t = $this->em->find(CodeTerrain::class, $id);
        if (!$t) {
            throw $this->createNotFoundException();
        }
        $motif = trim(mb_substr((string) ($this->payload($request)['motif'] ?? ''), 0, 500)) ?: null;
        if ($decision === 'refuser' && !$motif) {
            return $this->fail('Indiquez le motif du refus : l\'agriculteur le verra.');
        }
        $justificatifs->decider($t, $decision === 'valider', $motif, $this->getUser());
        return $this->ok(['message' => $decision === 'valider' ? 'Terrain validé.' : 'Justificatif refusé : l\'agriculteur est prévenu.']);
    }

    /** Boutons du panneau : lancer la veille, l'apprentissage ou le recalcul de tous les terrains. */
    #[Route('/api/admin/robot/{action}', name: 'api_admin_robot', methods: ['POST'], requirements: ['action' => 'veille|apprendre|analyser'], priority: 10)]
    public function robot(string $action, Request $request, \App\Robot\VeilleService $veille, \App\Robot\Apprentissage $apprentissage, \App\Robot\RobotService $robot): JsonResponse
    {
        $this->assertSameOrigin($request);
        set_time_limit(600);
        if ($action === 'veille') {
            $r = $veille->lancer();
            return $this->ok(['message' => sprintf('Veille : %d page(s), %d nouvelle(s) – %d prix, %d intrants, %d statistiques.%s', $r['pages'], $r['nouvelles'], $r['prix'], $r['intrants'], $r['statistiques'], $r['erreurs'] ? "\n" . implode("\n", $r['erreurs']) : '')]);
        }
        if ($action === 'apprendre') {
            $r = $apprentissage->apprendre();
            return $this->ok(['message' => sprintf('Apprentissage : %d récolte(s), %d calibration(s).', $r['recoltes'], $r['calibrations'])]);
        }
        $n = 0;
        $erreurs = [];
        foreach ($this->em->getRepository(CodeTerrain::class)->findAll() as $t) {
            try {
                $robot->analyserTerrain($t, false);
                ++$n;
            } catch (\Throwable $e) {
                $erreurs[] = $t->getNom() . ' : ' . $e->getMessage();
            }
        }
        return $this->ok(['message' => sprintf('%d terrain(s) recalculé(s).%s', $n, $erreurs ? "\n" . implode("\n", array_slice($erreurs, 0, 5)) : '')]);
    }

    #[Route('/api/admin/upload', name: 'api_admin_upload', methods: ['POST'], priority: 10)]
    public function upload(Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $file = $request->files->get('file');
        if (!$file || !$file->isValid()) {
            return $this->fail('Aucun fichier reçu (vérifiez upload_max_filesize dans php.ini).');
        }
        $types = [
            'video/mp4' => ['Video', 'mp4'], 'video/webm' => ['Video', 'webm'],
            'image/jpeg' => ['Images', 'jpg'], 'image/png' => ['Images', 'png'], 'image/webp' => ['Images', 'webp'],
            'application/pdf' => ['uploads/documents', 'pdf'],
            'application/msword' => ['uploads/documents', 'doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['uploads/documents', 'docx'],
        ];
        $t = $types[$file->getMimeType()] ?? null;
        if (!$t) {
            return $this->fail('Type de fichier non accepté : ' . $file->getMimeType());
        }
        $base = (new \Symfony\Component\String\Slugger\AsciiSlugger())->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))->lower()->slice(0, 60);
        $name = $base . '-' . bin2hex(random_bytes(3)) . '.' . $t[1];
        $file->move($this->getParameter('kernel.project_dir') . '/public/' . $t[0], $name);

        return $this->ok(['path' => '/' . $t[0] . '/' . $name]);
    }

    #[Route('/api/admin/{table}', name: 'api_admin_create', methods: ['POST'], requirements: ['table' => '[a-z]+'])]
    public function create(string $table, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        [$class, , $canCreate] = $this->table($table);
        if (!$canCreate) {
            return $this->fail('La création n\'est pas possible pour cette table.', 403);
        }
        $meta = $this->em->getClassMetadata($class);
        $ctor = (new \ReflectionClass($class))->getConstructor();
        if ($ctor && $ctor->getNumberOfRequiredParameters() > 0) {
            // entités à constructeur paramétré : instanciation brute puis dates techniques initialisées
            $entity = $meta->newInstance();
            foreach (['createdAt', 'updatedAt', 'lueLe'] as $f) {
                if ($meta->hasField($f) && !$meta->getReflectionProperty($f)->isInitialized($entity)) {
                    $meta->getReflectionProperty($f)->setValue($entity, new \DateTime());
                }
            }
        } else {
            $entity = new $class();
        }

        return $this->persist($meta, $entity, $this->payload($request), true);
    }

    #[Route('/api/admin/{table}/{id}', name: 'api_admin_update', methods: ['PUT', 'PATCH'], requirements: ['table' => '[a-z]+'])]
    public function update(string $table, string $id, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        [$class] = $this->table($table);
        $meta = $this->em->getClassMetadata($class);

        return $this->persist($meta, $this->find($meta, $id), $this->payload($request), false);
    }

    #[Route('/api/admin/{table}/{id}', name: 'api_admin_delete', methods: ['DELETE'], requirements: ['table' => '[a-z]+'])]
    public function delete(string $table, string $id, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        [$class] = $this->table($table);
        $meta = $this->em->getClassMetadata($class);
        $entity = $this->find($meta, $id);

        if ($entity === $this->getUser()) {
            return $this->fail('Vous ne pouvez pas supprimer votre propre compte ici.', 403);
        }

        try {
            $this->em->remove($entity);
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            return $this->fail('Impossible : cet enregistrement est utilisé ailleurs.', 409);
        }
        return $this->ok();
    }

    // ------------------------------------------------------------------

    private function persist(ClassMetadata $meta, object $entity, array $data, bool $isNew): JsonResponse
    {
        $errors = [];
        $choices = self::CHOICES[$meta->getName()] ?? [];

        if ($meta->getName() === PrixIntrant::class) {
            $choices['intrant'] = PrixIntrant::INTRANTS;
        }
        foreach ($meta->getFieldNames() as $f) {
            if (in_array($f, self::HIDDEN, true) || in_array($f, self::READONLY, true)) {
                continue;
            }
            $isId = $meta->isIdentifier($f);
            if ($isId && ($meta->usesIdGenerator() || !$isNew)) {
                continue; // id auto ou clé primaire non modifiable
            }
            if (!array_key_exists($f, $data)) {
                continue;
            }
            try {
                $value = $this->convert($meta, $f, $data[$f]);
            } catch (\InvalidArgumentException $e) {
                $errors[$f] = $e->getMessage();
                continue;
            }
            if ($value !== null && isset($choices[$f]) && !isset($choices[$f][$value])) {
                $errors[$f] = 'Valeur non autorisée';
                continue;
            }
            $meta->getReflectionProperty($f)->setValue($entity, $value);
        }

        foreach ($meta->getAssociationMappings() as $name => $assoc) {
            if (!$meta->isSingleValuedAssociation($name) || !array_key_exists($name, $data)) {
                continue;
            }
            $raw = $data[$name];
            if ($raw === '' || $raw === null) {
                if (!self::assocNullable($assoc)) {
                    $errors[$name] = 'Champ obligatoire';
                } else {
                    $meta->getReflectionProperty($name)->setValue($entity, null);
                }
                continue;
            }
            $target = $this->em->find($assoc->targetEntity, (int) $raw);
            if (!$target) {
                $errors[$name] = 'Référence introuvable (#' . (int) $raw . ')';
                continue;
            }
            $meta->getReflectionProperty($name)->setValue($entity, $target);
        }

        // Propriétés obligatoires non renseignées
        foreach ([...$meta->getFieldNames(), ...array_keys($meta->getAssociationMappings())] as $f) {
            $rp = $meta->getReflectionProperty($f);
            if ($rp && !$rp->isInitialized($entity) && $meta->hasField($f) && $meta->getTypeOfField($f) === 'boolean') {
                $rp->setValue($entity, false); // case à cocher non envoyée = non
                continue;
            }
            if ($rp && !$rp->isInitialized($entity) && !($meta->isIdentifier($f) && $meta->usesIdGenerator())) {
                $errors[$f] ??= 'Champ obligatoire';
            }
        }

        if (!$errors) {
            foreach ($this->validator->validate($entity) as $v) {
                $errors[$v->getPropertyPath()] = $v->getMessage();
            }
        }
        if ($errors) {
            return $this->fail('Veuillez corriger les champs indiqués.', 422, $errors);
        }

        if ($isNew) {
            $existing = $meta->usesIdGenerator() ? null : $this->em->find($meta->getName(), $meta->getIdentifierValues($entity));
            if ($existing) {
                return $this->fail('Un enregistrement avec cet identifiant existe déjà.', 409);
            }
            $this->em->persist($entity);
        }

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->fail('Valeur déjà utilisée (contrainte d\'unicité).', 409);
        }

        return $this->ok(['row' => $this->serialize($meta, $entity)], $isNew ? 201 : 200);
    }

    private function convert(ClassMetadata $meta, string $field, mixed $raw): mixed
    {
        $mapping = $meta->getFieldMapping($field);
        $nullable = (bool) $mapping->nullable;
        $type = $mapping->type;

        if ($raw === '' || $raw === null) {
            if ($type === 'boolean') {
                return false;
            }
            if ($type === 'json') {
                return [];
            }
            if ($nullable) {
                return null;
            }
            throw new \InvalidArgumentException('Champ obligatoire');
        }

        switch ($type) {
            case 'integer':
            case 'smallint':
            case 'bigint':
                if (!is_numeric($raw) || (int) $raw != $raw) {
                    throw new \InvalidArgumentException('Nombre entier attendu');
                }
                return (int) $raw;
            case 'float':
                $raw = str_replace(',', '.', (string) $raw);
                if (!is_numeric($raw)) {
                    throw new \InvalidArgumentException('Nombre attendu');
                }
                return (float) $raw;
            case 'decimal':
                $raw = str_replace(',', '.', (string) $raw);
                if (!is_numeric($raw)) {
                    throw new \InvalidArgumentException('Nombre attendu');
                }
                return (string) $raw;
            case 'boolean':
                return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
            case 'date':
            case 'datetime':
                $d = \DateTime::createFromFormat('!Y-m-d', (string) $raw)
                    ?: \DateTime::createFromFormat('Y-m-d\TH:i', (string) $raw)
                    ?: \DateTime::createFromFormat('Y-m-d H:i:s', (string) $raw);
                if (!$d) {
                    throw new \InvalidArgumentException('Date invalide (AAAA-MM-JJ)');
                }
                return $d;
            case 'json':
                if (is_array($raw)) {
                    return $raw;
                }
                $decoded = json_decode((string) $raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                return array_values(array_filter(array_map('trim', explode(',', (string) $raw)), 'strlen'));
            default:
                $s = trim((string) $raw);
                if ($mapping->length && mb_strlen($s) > $mapping->length) {
                    throw new \InvalidArgumentException('Maximum ' . $mapping->length . ' caractères');
                }
                return $s;
        }
    }

    private function columns(ClassMetadata $meta): array
    {
        $cols = [];
        $choices = self::CHOICES[$meta->getName()] ?? [];
        if ($meta->getName() === PrixIntrant::class) {
            $choices['intrant'] = array_map(fn ($x) => $x[0] . ' (DT/' . $x[1] . ')', PrixIntrant::INTRANTS);
        }

        if ($meta->getName() === Utilisateur::class) {
            $cols[] = ['id' => 'typeCompte', 'name' => 'Type', 'type' => 'string', 'editable' => false, 'isId' => false, 'nullable' => true];
        }

        foreach ($meta->getFieldNames() as $f) {
            if (in_array($f, self::HIDDEN, true)) {
                continue;
            }
            $m = $meta->getFieldMapping($f);
            $isId = $meta->isIdentifier($f);
            $cols[] = [
                'id' => $f,
                'name' => self::LABELS[$f] ?? ucfirst(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $f))),
                'type' => $m->type,
                'nullable' => (bool) $m->nullable,
                'isId' => $isId,
                'generated' => $isId && $meta->usesIdGenerator(),
                // une clé primaire saisie à la main ne se renseigne qu'à la création
                'editable' => !in_array($f, self::READONLY, true) && !($isId && $meta->usesIdGenerator()),
                'createOnly' => $isId && !$meta->usesIdGenerator(),
                'choices' => $choices[$f] ?? null,
                'file' => in_array($f, ['formationVideo', 'formationPdf', 'formationWord', 'posterPath'], true),
            ];
        }
        foreach ($meta->getAssociationMappings() as $name => $assoc) {
            if (!$meta->isSingleValuedAssociation($name)) {
                continue;
            }
            $cols[] = [
                'id' => $name,
                'name' => ucfirst($name) . ' (id)',
                'type' => 'integer',
                'nullable' => self::assocNullable($assoc),
                'isId' => false,
                'editable' => true,
                'createOnly' => false,
                'choices' => null,
                'file' => false,
                'relation' => true,
            ];
        }
        return $cols;
    }

    private function serialize(ClassMetadata $meta, object $e): array
    {
        $row = [];
        foreach ($meta->getFieldNames() as $f) {
            if (in_array($f, self::HIDDEN, true)) {
                continue;
            }
            $rp = $meta->getReflectionProperty($f);
            $v = $rp->isInitialized($e) ? $rp->getValue($e) : null;
            if ($v instanceof \DateTimeInterface) {
                $v = $meta->getTypeOfField($f) === 'date' ? $v->format('Y-m-d') : $v->format('Y-m-d H:i');
            } elseif (is_array($v)) {
                $v = implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x), $v));
            }
            $row[$f] = $v;
        }
        foreach ($meta->getAssociationMappings() as $name => $assoc) {
            if ($meta->isSingleValuedAssociation($name)) {
                $rp = $meta->getReflectionProperty($name);
                $target = $rp->isInitialized($e) ? $rp->getValue($e) : null;
                $row[$name] = $target ? implode('-', $this->em->getClassMetadata($assoc->targetEntity)->getIdentifierValues($target)) : null;
            }
        }
        if ($e instanceof Utilisateur) {
            $row['typeCompte'] = $e->getTypeCompte();
        }
        $row['_id'] = implode('-', array_map('strval', $meta->getIdentifierValues($e)));
        return $row;
    }

    private function find(ClassMetadata $meta, string $id): object
    {
        $fields = $meta->getIdentifierFieldNames();
        $parts = count($fields) > 1 ? explode('-', $id) : [$id];
        if (count($parts) !== count($fields)) {
            throw new NotFoundHttpException();
        }
        $entity = $this->em->find($meta->getName(), array_combine($fields, array_map('intval', $parts)));
        if (!$entity) {
            throw new NotFoundHttpException('Enregistrement introuvable');
        }
        return $entity;
    }

    private static function assocNullable(object $assoc): bool
    {
        $jc = property_exists($assoc, 'joinColumns') ? ($assoc->joinColumns[0] ?? null) : null;
        return $jc === null || $jc->nullable !== false;
    }

    private function table(string $table): array
    {
        if (!isset(self::TABLES[$table])) {
            throw new NotFoundHttpException('Table inconnue');
        }
        return self::TABLES[$table];
    }
}

<?php

namespace App\Controller;

use App\Entity\DemandeFormateur;
use App\Entity\Formation;
use App\Entity\RegularUtilisateur;
use App\Entity\Utilisateur;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Formateurs :
 *  - page « Devenir formateur » (candidature, ouverte à tous) ;
 *  - page de connexion des formateurs ;
 *  - espace formateur : publier des vidéos et des PDF, qui apparaissent aussitôt dans la page Formations.
 * Un candidat sans compte choisit son mot de passe ; son compte est créé quand l'équipe accepte la candidature.
 */
final class FormateurController extends AbstractController
{
    use JsonApiTrait;

    public const VIDEO_MAX = 300 * 1024 * 1024;
    public const PDF_MAX = 20 * 1024 * 1024;
    private const VIDEOS = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
    /** Documents acceptés : PDF et Word (doc, docx, odt). */
    private const DOCUMENTS = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.oasis.opendocument.text' => 'odt',
    ];
    public const PRIX_MAX = 5000;
    private const NIVEAUX = ['debutant' => 'Débutant', 'intermediaire' => 'Intermédiaire', 'avance' => 'Avancé'];

    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public/uploads/formations')] private string $dossier,
    ) {
    }

    // ------------------------------------------------------------------ pages

    #[Route('/devenir-formateur', name: 'app_devenir_formateur', methods: ['GET'])]
    public function devenir(): Response
    {
        $u = $this->getUser();
        $demande = $u instanceof Utilisateur
            ? $this->em->getRepository(DemandeFormateur::class)->findOneBy(['utilisateur' => $u], ['createdAt' => 'DESC'])
            : null;
        return $this->render('formateur/devenir.html.twig', [
            'demande' => $demande,
            'domaines' => DemandeFormateur::DOMAINES,
            'formats' => DemandeFormateur::FORMATS,
        ]);
    }

    #[Route('/formateur/connexion', name: 'app_formateur_login', methods: ['GET', 'POST'])] // le POST est traité par LoginAuthenticator
    public function connexion(AuthenticationUtils $auth): Response
    {
        if ($this->getUser() instanceof Utilisateur) {
            return $this->redirectToRoute('app_espace_formateur');
        }
        return $this->render('formateur/connexion.html.twig', [
            'erreur' => $auth->getLastAuthenticationError(),
            'dernier' => $auth->getLastUsername(),
        ]);
    }

    #[Route('/espace-formateur', name: 'app_espace_formateur', methods: ['GET'])]
    public function espace(): Response
    {
        /** @var Utilisateur $u */
        $u = $this->getUser();
        $demande = $this->em->getRepository(DemandeFormateur::class)->findOneBy(['email' => $u->getEmail()], ['createdAt' => 'DESC']);
        // ventes confirmées par formation : [id => [nombre, total DT]]
        $ventes = [];
        foreach ($this->em->createQuery('SELECT IDENTITY(a.formation) AS f, COUNT(a.id) AS n, SUM(a.prix) AS total FROM ' . \App\Entity\AchatFormation::class . " a JOIN a.formation fo WHERE fo.auteur = :u AND a.statut = 'payee' GROUP BY a.formation")
                     ->setParameter('u', $u)->getArrayResult() as $r) {
            $ventes[(int) $r['f']] = ['n' => (int) $r['n'], 'total' => (float) $r['total']];
        }
        return $this->render('formateur/espace.html.twig', [
            'demande' => $demande,
            'ventes' => $ventes,
            'formations' => $u->isFormateur() ? $this->em->getRepository(Formation::class)->findBy(['auteur' => $u], ['createdAt' => 'DESC']) : [],
            'niveaux' => self::NIVEAUX,
            'types' => ['Céréales', 'Légumineuses', 'Légumes', 'Fruits', 'Arboriculture', 'Olives', 'Oléagineux', 'Élevage', 'Apiculture', 'Irrigation', 'Sol et fertilisation', 'Machinisme', 'Gestion'],
            'videoMaxMo' => (int) min(self::VIDEO_MAX, self::limitePhp()) / 1024 / 1024,
        ]);
    }

    // ------------------------------------------------------------------ candidature

    /** Candidature « Devenir formateur ». Visiteur : avec mot de passe. Membre connecté : sans. */
    #[Route('/api/formateur', name: 'api_formateur', methods: ['POST'])]
    public function candidature(Request $request, UserPasswordHasherInterface $hasher, NotificationService $notifications): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        if (self::str($d, 'site') !== '') {
            return $this->ok([], 201); // champ piège anti-robots rempli : on ignore
        }
        $user = $this->getUser();
        $user = $user instanceof Utilisateur ? $user : null;
        if ($user?->isFormateur()) {
            return $this->fail('Vous êtes déjà formateur : allez dans votre espace formateur.', 409);
        }

        $nom = $user ? $user->getNomAffiche() : self::str($d, 'nom', 150);
        $email = $user ? $user->getEmail() : mb_strtolower(self::str($d, 'email', 180));
        $lien = self::str($d, 'lien', 255);
        $errors = [];
        if (mb_strlen($nom) < 3) {
            $errors['nom'] = 'Indiquez votre nom complet.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse email invalide.';
        }
        if (!$user) {
            if ($this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email])) {
                $errors['email'] = 'Un compte existe déjà avec cet email : connectez-vous d\'abord, puis envoyez votre candidature.';
            }
            $mdp = (string) ($d['motDePasse'] ?? '');
            if (mb_strlen($mdp) < 8 || !preg_match('/\d/', $mdp)) {
                $errors['motDePasse'] = 'Le mot de passe doit contenir au moins 8 caractères dont un chiffre.';
            } elseif ($mdp !== (string) ($d['confirmation'] ?? '')) {
                $errors['confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
            }
            if (!preg_match('/^\+?[\d\s]{8,20}$/', self::str($d, 'telephone', 30))) {
                $errors['telephone'] = 'Indiquez un numéro de téléphone valide.';
            }
        }
        if ($lien !== '' && !filter_var($lien, FILTER_VALIDATE_URL)) {
            $errors['lien'] = 'Le lien doit commencer par http:// ou https://';
        }
        $domaines = array_values(array_intersect((array) ($d['domaines'] ?? []), array_keys(DemandeFormateur::DOMAINES)));
        if (!$domaines) {
            $errors['domaines'] = 'Choisissez au moins un domaine.';
        }
        $enCours = $this->em->getRepository(DemandeFormateur::class)->findOneBy(['email' => $email, 'statut' => ['nouvelle', 'en_cours']]);
        if ($enCours) {
            $errors['email'] = 'Une candidature avec cet email est déjà en cours d\'examen.';
        }
        if ($errors) {
            return $this->fail(reset($errors), 422, $errors);
        }

        $f = (new DemandeFormateur())
            ->setUtilisateur($user)
            ->setNom($nom)->setEmail($email)
            ->setTelephone($user ? $user->getTelephone() : self::str($d, 'telephone', 30))
            ->setProfession(self::str($d, 'profession', 150) ?: null)
            ->setDomaines($domaines)
            ->setFormats((array) ($d['formats'] ?? []))
            ->setAnneesExperience(($d['experience'] ?? '') !== '' ? max(0, min(70, (int) $d['experience'])) : null)
            ->setLienExemple($lien ?: null)
            ->setMessage(self::str($d, 'message', 3000) ?: null);
        if (!$user) {
            $f->setMotDePasse($hasher->hashPassword(new RegularUtilisateur(), (string) $d['motDePasse']));
        }
        $this->em->persist($f);
        if ($user) {
            $notifications->envoyerA($user, 'compte', 'Candidature de formateur reçue', 'Merci ! L\'équipe PeasantGo.AI étudie votre candidature. Vous serez prévenu ici et par email.', '/espace-formateur');
        }
        $this->em->flush();
        return $this->ok(['message' => 'Merci ! Votre candidature est envoyée. Quand l\'équipe l\'aura acceptée, connectez-vous avec « Déjà formateur ? ».'], 201);
    }

    // ------------------------------------------------------------------ publications du formateur

    /** Nouvelle formation : titre, type, culture, niveau, durée, description + vidéo et/ou PDF (multipart). */
    #[Route('/api/formateur/formations', name: 'api_formateur_formation_add', methods: ['POST'])]
    public function publier(Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $u = $this->formateur();
        $d = $request->request->all();
        $video = $request->files->get('video');
        $doc = $request->files->get('document');
        $errors = [];
        foreach (['video' => $video, 'document' => $doc] as $k => $f) {
            if ($f instanceof UploadedFile && !$f->isValid()) {
                $errors[$k] = in_array($f->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                    ? sprintf('Le fichier est trop lourd pour le serveur (limite actuelle : %d Mo).', self::limitePhp() / 1024 / 1024)
                    : 'Le fichier n\'a pas pu être reçu, réessayez.';
            }
        }
        if ($request->request->count() === 0 && $request->files->count() === 0 && (int) $request->server->get('CONTENT_LENGTH') > 0) {
            return $this->fail(sprintf('Les fichiers dépassent la limite du serveur (%d Mo au total).', self::limitePostPhp() / 1024 / 1024), 413);
        }
        $titre = self::str($d, 'titre', 200);
        $type = self::str($d, 'type', 100);
        $sousType = self::str($d, 'sousType', 120);
        if (mb_strlen($titre) < 5) {
            $errors['titre'] = 'Donnez un titre d\'au moins 5 caractères.';
        }
        if ($type === '' || $sousType === '') {
            $errors['type'] = 'Choisissez le thème et indiquez la culture ou l\'élevage concerné.';
        }
        if (!($video instanceof UploadedFile) && !($doc instanceof UploadedFile)) {
            $errors['video'] = 'Ajoutez au moins une vidéo ou un document (PDF ou Word).';
        }
        $payante = ($d['acces'] ?? 'gratuite') === 'payante';
        $prix = null;
        if ($payante) {
            $prix = (float) str_replace(',', '.', (string) ($d['prix'] ?? ''));
            if ($prix <= 0 || $prix > self::PRIX_MAX) {
                $errors['prix'] = sprintf('Indiquez le prix de la formation (entre 1 et %d DT).', self::PRIX_MAX);
            }
        }
        if ($video instanceof UploadedFile && $video->isValid()) {
            if (!isset(self::VIDEOS[$video->getMimeType() ?? ''])) {
                $errors['video'] = 'Vidéo acceptée : MP4, WEBM ou MOV.';
            } elseif ($video->getSize() > self::VIDEO_MAX) {
                $errors['video'] = sprintf('La vidéo dépasse %d Mo.', self::VIDEO_MAX / 1024 / 1024);
            }
        }
        $docExt = null;
        if ($doc instanceof UploadedFile && $doc->isValid()) {
            $docExt = self::extensionDocument($doc);
            if ($docExt === null) {
                $errors['document'] = 'Document accepté : PDF ou Word (DOC, DOCX, ODT).';
            } elseif ($doc->getSize() > self::PDF_MAX) {
                $errors['document'] = sprintf('Le document dépasse %d Mo.', self::PDF_MAX / 1024 / 1024);
            }
        }
        if ($errors) {
            return $this->fail(reset($errors), 422, $errors);
        }

        $f = (new Formation())
            ->setAuteur($u)
            ->setTitle($titre)
            ->setType($type)
            ->setSousType($sousType)
            ->setCategorie(in_array($type, ['Élevage', 'Apiculture'], true) ? 'Animale' : 'Végétale')
            ->setDifficulty(isset(self::NIVEAUX[$d['niveau'] ?? '']) ? $d['niveau'] : 'debutant')
            ->setDuration(($d['duree'] ?? '') !== '' ? max(1, min(600, (int) $d['duree'])) : null)
            ->setDescription(self::str($d, 'description', 3000) ?: null)
            ->setPayante($payante)
            ->setPrix($payante ? round($prix, 3) : null);
        if ($video instanceof UploadedFile) {
            $f->setFormationVideo($this->ranger($video, self::VIDEOS[$video->getMimeType()]));
        }
        if ($doc instanceof UploadedFile && $docExt !== null) {
            $chemin = $this->ranger($doc, $docExt);
            $docExt === 'pdf' ? $f->setFormationPdf($chemin) : $f->setFormationWord($chemin);
        }
        $this->em->persist($f);
        $this->em->flush();
        return $this->ok(['message' => 'Formation publiée : elle apparaît déjà dans la page Formations.', 'id' => $f->getId()], 201);
    }

    #[Route('/api/formateur/formations/{id}/{action}', name: 'api_formateur_formation_action', methods: ['POST'], requirements: ['id' => '\d+', 'action' => 'masquer|afficher|supprimer'])]
    public function action(int $id, string $action, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $u = $this->formateur();
        $f = $this->em->find(Formation::class, $id);
        if (!$f || $f->getAuteur() !== $u) {
            throw $this->createNotFoundException();
        }
        if ($action === 'supprimer') {
            foreach ([$f->getFormationVideo(), $f->getFormationPdf(), $f->getFormationWord()] as $chemin) {
                if ($chemin && str_starts_with($chemin, '/uploads/formations/') && is_file($this->dossier . '/' . basename($chemin))) {
                    @unlink($this->dossier . '/' . basename($chemin));
                }
            }
            $this->em->remove($f);
            $message = 'Formation supprimée.';
        } else {
            $f->setActive($action === 'afficher');
            $message = $action === 'afficher' ? 'La formation est de nouveau visible.' : 'La formation est masquée.';
        }
        $this->em->flush();
        return $this->ok(['message' => $message]);
    }

    // ------------------------------------------------------------------ outils

    private function formateur(): Utilisateur
    {
        $u = $this->getUser();
        if (!$u instanceof Utilisateur || !$u->isFormateur()) {
            throw $this->createAccessDeniedException('Réservé aux formateurs.');
        }
        return $u;
    }

    /** Extension sûre du document, d'après son contenu réel (un .docx est lu comme une archive zip). */
    private static function extensionDocument(UploadedFile $f): ?string
    {
        $mime = (string) $f->getMimeType();
        if (isset(self::DOCUMENTS[$mime])) {
            return self::DOCUMENTS[$mime];
        }
        $ext = strtolower($f->getClientOriginalExtension());
        if (in_array($mime, ['application/zip', 'application/octet-stream'], true) && in_array($ext, ['docx', 'odt'], true)) {
            return $ext;
        }
        if ($mime === 'application/CDFV2' && $ext === 'doc') {
            return 'doc';
        }
        return null;
    }

    private function ranger(UploadedFile $f, string $ext): string
    {
        if (!is_dir($this->dossier)) {
            mkdir($this->dossier, 0755, true);
        }
        $nom = bin2hex(random_bytes(12)) . '.' . $ext;
        $f->move($this->dossier, $nom);
        return '/uploads/formations/' . $nom;
    }

    /** Limites d'envoi de PHP (php.ini : upload_max_filesize et post_max_size). */
    public static function limitePhp(): int
    {
        return self::octets((string) ini_get('upload_max_filesize'));
    }

    public static function limitePostPhp(): int
    {
        return self::octets((string) ini_get('post_max_size'));
    }

    private static function octets(string $v): int
    {
        $n = (int) $v;
        return match (strtolower(substr(trim($v), -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}

<?php

namespace App\Controller;

use App\Entity\AchatFormation;
use App\Entity\Formation;
use App\Entity\Utilisateur;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Formations payantes :
 *  - aperçu : l'agriculteur connecté voit les 25 % premiers de la vidéo (les documents restent fermés) ;
 *  - achat : lié à son compte, donc valable avec tous ses terrains ;
 *  - paiement : D17, Flouci, virement ou mandat, vérifié par l'équipe (PAIEMENT_DEMO=1 valide tout de suite, pour les essais).
 */
final class AchatFormationController extends AbstractController
{
    use JsonApiTrait;

    public const APERCU = 0.25;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationService $notifications,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%env(default::PAIEMENT_DEMO)%')] private readonly ?string $paiementDemo = null,
        #[Autowire('%env(default::PAIEMENT_COMPTE)%')] private readonly ?string $paiementCompte = null,
    ) {
    }

    /** Statut d'achat par formation pour cet utilisateur : [id => 'payee'|'en_attente'|'refusee']. */
    public static function statuts(EntityManagerInterface $em, ?Utilisateur $u): array
    {
        if (!$u) {
            return [];
        }
        $out = [];
        foreach ($em->createQuery('SELECT IDENTITY(a.formation) AS f, a.statut FROM ' . AchatFormation::class . ' a WHERE a.utilisateur = :u')
                     ->setParameter('u', $u)->getArrayResult() as $r) {
            $out[(int) $r['f']] = $r['statut'];
        }
        return $out;
    }

    /** L'agriculteur déclare son paiement. */
    #[Route('/api/formations/{id}/acheter', name: 'api_formation_acheter', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function acheter(Formation $formation, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $u = $this->getUser();
        if (!$u instanceof Utilisateur) {
            return $this->fail('Connectez-vous pour acheter une formation.', 401);
        }
        if (!$formation->isActive() || !$formation->isPayante()) {
            return $this->fail('Cette formation n\'est pas en vente.');
        }
        if ($formation->getAuteur()?->getId() === $u->getId()) {
            return $this->fail('C\'est votre propre formation.');
        }
        $repo = $this->em->getRepository(AchatFormation::class);
        $achat = $repo->findOneBy(['utilisateur' => $u, 'formation' => $formation]);
        if ($achat?->isPayee()) {
            return $this->ok(['message' => 'Vous avez déjà acheté cette formation.', 'statut' => 'payee']);
        }
        $d = $this->payload($request);
        $moyen = (string) ($d['moyen'] ?? '');
        $reference = trim(mb_substr((string) ($d['reference'] ?? ''), 0, 120));
        $demo = $this->paiementDemo === '1';
        if (!$demo) {
            if (!isset(AchatFormation::MOYENS[$moyen])) {
                return $this->fail('Choisissez le moyen de paiement.', 422, ['moyen' => 'requis']);
            }
            if (mb_strlen($reference) < 4) {
                return $this->fail('Indiquez la référence de votre paiement (numéro de transaction ou de reçu).', 422, ['reference' => 'requis']);
            }
        }
        if (!$achat) {
            $achat = new AchatFormation($u, $formation);
            $this->em->persist($achat);
        }
        $achat->declarer(isset(AchatFormation::MOYENS[$moyen]) ? $moyen : null, $reference ?: null);
        if ($demo) {
            $achat->confirmer();
        }
        $this->em->flush();
        return $this->ok($demo
            ? ['message' => 'Paiement accepté : la formation est maintenant ouverte en entier sur votre compte.', 'statut' => 'payee']
            : ['message' => 'Merci ! Votre paiement est en cours de vérification. Vous serez prévenu dès que la formation sera ouverte.', 'statut' => 'en_attente']);
    }

    /** Instructions de paiement affichées dans la fenêtre d'achat. */
    #[Route('/api/formations/paiement', name: 'api_formation_paiement', methods: ['GET'], priority: 5)]
    public function paiement(): JsonResponse
    {
        return $this->ok(['demo' => $this->paiementDemo === '1', 'compte' => (string) $this->paiementCompte, 'moyens' => AchatFormation::MOYENS]);
    }

    /**
     * Fichiers des formations payantes, servis par le site (le chemin réel n'est jamais montré) :
     *  - video : tout membre connecté (l'aperçu est limité à 25 % par le lecteur), contenu entier si acheté ;
     *  - document : seulement si la formation est ouverte (achetée, auteur, admin) ;
     *  - apercu : le PDF pour l'aperçu (le lecteur n'affiche que 25 % des pages, sans téléchargement).
     */
    #[Route('/formation/{id}/media/{type}', name: 'app_formation_media', methods: ['GET'], requirements: ['id' => '\d+', 'type' => 'video|document|apercu'])]
    public function media(Formation $formation, string $type): BinaryFileResponse
    {
        $u = $this->getUser();
        if (!$u instanceof Utilisateur || !$formation->isActive()) {
            throw $this->createAccessDeniedException();
        }
        $ouvert = $formation->estAccessiblePour($u, self::statuts($this->em, $u));
        $chemin = match ($type) {
            'video' => $formation->getFormationVideo(),
            'apercu' => $formation->getFormationPdf(),
            default => $formation->getFormationPdf() ?: $formation->getFormationWord(),
        };
        if (!$chemin || ($type === 'document' && !$ouvert)) {
            throw $this->createNotFoundException();
        }
        $fichier = $this->projectDir . '/public/' . ltrim($chemin, '/');
        if (!str_starts_with($chemin, '/uploads/') || str_contains($chemin, '..') || !is_file($fichier)) {
            throw $this->createNotFoundException();
        }
        $r = new BinaryFileResponse($fichier);
        $r->setPrivate();
        $r->headers->set('X-Content-Type-Options', 'nosniff');
        if ($type === 'document') {
            $nom = preg_replace('/[^A-Za-z0-9 _-]+/', '', (string) $formation->getTitle()) ?: 'formation';
            $r->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $nom . '.' . pathinfo($fichier, PATHINFO_EXTENSION));
        }
        return $r;
    }

    /** Aperçu d'un document Word : seuls les 25 % premiers paragraphes sont envoyés. */
    #[Route('/api/formations/{id}/apercu-texte', name: 'api_formation_apercu_texte', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function apercuTexte(Formation $formation): JsonResponse
    {
        if (!$this->getUser() instanceof Utilisateur || !$formation->isActive() || !$formation->getFormationWord()) {
            throw $this->createNotFoundException();
        }
        $chemin = (string) $formation->getFormationWord();
        $fichier = $this->projectDir . '/public/' . ltrim($chemin, '/');
        if (!str_starts_with($chemin, '/uploads/') || str_contains($chemin, '..') || !is_file($fichier)) {
            throw $this->createNotFoundException();
        }
        $a = \App\Formation\ApercuDocument::word($fichier, self::APERCU);
        if ($a === null) {
            return $this->fail('L\'aperçu n\'est pas disponible pour ce format de document.', 415);
        }
        return $this->ok($a);
    }

    // ------------------------------------------------------------------ équipe (admin)

    #[Route('/api/admin/achats-formations', name: 'api_admin_achats', methods: ['GET'], priority: 10)]
    public function enAttente(): JsonResponse
    {
        $liste = $this->em->getRepository(AchatFormation::class)->findBy(['statut' => 'en_attente'], ['createdAt' => 'ASC'], 100);
        return $this->ok(['achats' => array_map(fn (AchatFormation $a) => [
            'id' => $a->getId(),
            'client' => $a->getUtilisateur()->getNomAffiche(),
            'email' => $a->getUtilisateur()->getEmail(),
            'telephone' => $a->getUtilisateur()->getTelephone(),
            'formation' => $a->getFormation()->getTitle(),
            'formateur' => $a->getFormation()->getAuteur()?->getNomAffiche(),
            'prix' => $a->getPrix(),
            'moyen' => AchatFormation::MOYENS[$a->getMoyen() ?? ''] ?? '—',
            'reference' => $a->getReference(),
            'le' => $a->getCreatedAt()->format('Y-m-d H:i'),
        ], $liste)]);
    }

    #[Route('/api/admin/achats-formations/{id}/{decision}', name: 'api_admin_achat_decision', methods: ['POST'], requirements: ['id' => '\d+', 'decision' => 'confirmer|refuser'], priority: 10)]
    public function decision(int $id, string $decision, Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $a = $this->em->find(AchatFormation::class, $id);
        if (!$a || $a->getStatut() !== 'en_attente') {
            throw $this->createNotFoundException();
        }
        $titre = (string) $a->getFormation()->getTitle();
        if ($decision === 'confirmer') {
            $a->confirmer();
            $this->notifications->envoyerA($a->getUtilisateur(), 'compte', 'Formation ouverte', 'Votre paiement est confirmé : « ' . $titre . ' » est maintenant ouverte en entier sur votre compte.', '/formation/user');
            if ($auteur = $a->getFormation()->getAuteur()) {
                $this->notifications->envoyerA($auteur, 'compte', 'Nouvelle vente', '« ' . $titre . ' » a été achetée.', '/espace-formateur');
            }
        } else {
            $a->refuser();
            $motif = trim(mb_substr((string) ($this->payload($request)['motif'] ?? ''), 0, 300));
            $this->notifications->envoyerA($a->getUtilisateur(), 'compte', 'Paiement non retrouvé', 'Nous n\'avons pas retrouvé votre paiement pour « ' . $titre . ' »' . ($motif ? ' : ' . $motif : '.') . ' Vous pouvez le déclarer à nouveau.', '/formation/user');
        }
        $this->em->flush();
        return $this->ok(['message' => $decision === 'confirmer' ? 'Achat confirmé : la formation est ouverte pour ce client.' : 'Achat refusé, le client est prévenu.']);
    }
}

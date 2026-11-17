<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Utilisateur;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use App\Service\StatistiquesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationController extends AbstractController
{
    use JsonApiTrait;

    public static function toArray(Notification $n): array
    {
        return [
            'id' => $n->getId(),
            'type' => $n->getType(),
            'titre' => $n->getTitre(),
            'message' => $n->getMessage(),
            'lien' => $n->getLien(),
            'lue' => $n->isLue(),
            'date' => $n->getCreatedAt()->format('Y-m-d H:i'),
        ];
    }

    /** Cloche : dernières notifications + nombre de non lues (appelé toutes les minutes). */
    #[Route('/api/notifications', name: 'api_notifications', methods: ['GET'])]
    public function list(NotificationRepository $repo): JsonResponse
    {
        $u = $this->user();
        return $this->ok([
            'nonLues' => $repo->countNonLues($u),
            'items' => array_map([self::class, 'toArray'], $repo->findRecentes($u)),
        ]);
    }

    #[Route('/api/notifications/lire', name: 'api_notifications_lire_tout', methods: ['POST'])]
    public function lireTout(Request $request, NotificationRepository $repo): JsonResponse
    {
        $this->assertSameOrigin($request);
        $repo->marquerToutLu($this->user());
        return $this->ok(['nonLues' => 0]);
    }

    #[Route('/api/notifications/{id}/lire', name: 'api_notification_lire', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function lire(int $id, Request $request, NotificationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->assertSameOrigin($request);
        $n = $repo->find($id);
        if (!$n || $n->getUtilisateur() !== $this->user()) {
            throw new NotFoundHttpException();
        }
        $n->setLue(true);
        $em->flush();
        return $this->ok(['nonLues' => $repo->countNonLues($this->user())]);
    }

    // ---------- Admin ----------

    /** Bouton « Publier les statistiques » : compare et notifie tout le monde. */
    #[Route('/api/admin/notifications/statistiques', name: 'api_admin_notif_stats', methods: ['POST'], priority: 10)]
    public function publierStatistiques(Request $request, StatistiquesService $stats): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        $seuil = max(0, min(100, (float) ($d['seuil'] ?? 5)));
        $r = $stats->publier($seuil, !empty($d['email']), !empty($d['forcer']), !empty($d['simulation']));

        if ($r['statut'] === 'aucun') {
            return $this->ok(['statut' => 'aucun', 'message' => "Aucune variation d'au moins $seuil % depuis la dernière publication. Cochez « Envoyer même sans changement » pour forcer."]);
        }
        $r['corps'] = $r['message'] ?? '';
        unset($r['message']);
        return $this->ok($r + ['message' => $r['statut'] === 'simulation'
            ? 'Aperçu (rien envoyé).'
            : sprintf('Envoyé : %d notification(s), %d email(s).', $r['envoi']['notifications'], $r['envoi']['emails'])]);
    }

    /** Annonce libre envoyée à tous les utilisateurs. */
    #[Route('/api/admin/notifications/annonce', name: 'api_admin_notif_annonce', methods: ['POST'], priority: 10)]
    public function annonce(Request $request, NotificationService $notifications): JsonResponse
    {
        $this->assertSameOrigin($request);
        $d = $this->payload($request);
        $titre = self::str($d, 'titre', 150);
        $message = self::str($d, 'message', 5000);
        $lien = self::str($d, 'lien');

        if (mb_strlen($titre) < 3 || mb_strlen($message) < 5) {
            return $this->fail('Titre (3 caractères min.) et message (5 caractères min.) obligatoires.');
        }
        if ($lien !== '' && !str_starts_with($lien, '/') && !filter_var($lien, FILTER_VALIDATE_URL)) {
            return $this->fail('Le lien doit commencer par / ou être une URL complète.');
        }

        $r = $notifications->envoyerATous('annonce', $titre, $message, $lien ?: null, !empty($d['email']));
        return $this->ok(['envoi' => $r, 'message' => sprintf('Annonce envoyée : %d notification(s), %d email(s).', $r['notifications'], $r['emails'])]);
    }

    private function user(): Utilisateur
    {
        /** @var Utilisateur $u */
        $u = $this->getUser();
        return $u;
    }
}

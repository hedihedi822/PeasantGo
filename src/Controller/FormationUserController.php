<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FormationUserController extends AbstractController
{
    use JsonApiTrait;

    #[Route('/formation/user', name: 'app_formation_user', methods: ['GET'])]
    public function index(Request $request, FormationRepository $formations, PaginatorInterface $paginator, EntityManagerInterface $em): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        return $this->render('formation_user/formation_user.html.twig', [
            'formations' => $paginator->paginate($formations->createSearchQuery($q), $request->query->getInt('page', 1), 6),
            'q' => $q,
            'achats' => AchatFormationController::statuts($em, $this->getUser() instanceof \App\Entity\Utilisateur ? $this->getUser() : null),
            'apercu' => AchatFormationController::APERCU,
        ]);
    }

    /** Compteur de vues : appelé quand une vidéo commence à être lue. */
    #[Route('/api/formations/{id}/vue', name: 'api_formation_vue', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function vue(Formation $formation, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->assertSameOrigin($request);
        $session = $request->getSession();
        $vues = $session->get('formations_vues', []);
        if (!in_array($formation->getId(), $vues, true)) {
            $formation->incrementViews();
            $em->flush();
            $vues[] = $formation->getId();
            $session->set('formations_vues', $vues);
        }
        return $this->ok(['views' => $formation->getViews()]);
    }
}

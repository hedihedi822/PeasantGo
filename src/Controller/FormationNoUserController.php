<?php

namespace App\Controller;

use App\Repository\FormationRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FormationNoUserController extends AbstractController
{
    #[Route('/formation/no/user', name: 'app_formation_no_user', methods: ['GET'])]
    public function index(Request $request, FormationRepository $formations, PaginatorInterface $paginator): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_formation_user', $request->query->all());
        }

        $q = trim((string) $request->query->get('q', ''));

        return $this->render('formation_no_user/formation_no_user.html.twig', [
            'formations' => $paginator->paginate($formations->createSearchQuery($q), $request->query->getInt('page', 1), 6),
            'q' => $q,
        ]);
    }
}

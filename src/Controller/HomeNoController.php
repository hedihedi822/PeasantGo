<?php

namespace App\Controller;

use App\Service\CatalogueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Accueil public (visiteur non connecté). */
final class HomeNoController extends AbstractController
{
    #[Route('/', name: 'app_home_no', methods: ['GET'])]
    public function index(CatalogueService $catalogue): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home_user');
        }

        return $this->render('home_no/home_no_user.html.twig', [
            'tableaux' => $catalogue->getTableaux(complet: false),
        ]);
    }
}

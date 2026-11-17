<?php

namespace App\Controller;

use App\Entity\Admin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    /**
     * GET : affiche le formulaire. POST : intercepté par App\Security\LoginAuthenticator.
     */
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function index(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($user = $this->getUser()) {
            return $this->redirectToRoute($user instanceof Admin ? 'app_admin' : 'app_choix_terrain');
        }

        return $this->render('login/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'account_type' => $request->getSession()->get('login_account_type', 'person'),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        // Intercepté par le firewall (security.yaml > logout)
        throw new \LogicException('Cette méthode est interceptée par le firewall.');
    }
}

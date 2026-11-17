<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Langue\Langues;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LangueController extends AbstractController
{
    /** Sélecteur de langue de la barre du haut (et des paramètres). */
    #[Route('/langue/{code}', name: 'app_langue', methods: ['GET'], requirements: ['code' => 'fr|ar|en'])]
    public function changer(string $code, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $retour = $request->headers->get('referer');
        if (!$retour || parse_url($retour, PHP_URL_HOST) !== $request->getHost()) {
            $retour = $this->generateUrl($this->getUser() ? 'app_home_user' : 'app_home_no');
        }
        $u = $this->getUser();
        if ($u instanceof Utilisateur) {
            $u->setPreferences(['langue' => $code] + $u->getPreferences());
            $em->flush();
        }
        $r = new RedirectResponse($retour);
        Langues::appliquer($request, $r, $code);
        return $r;
    }
}

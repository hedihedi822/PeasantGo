<?php

namespace App\EventListener;

use App\Entity\Admin;
use App\Entity\Utilisateur;
use App\Terrain\TerrainActif;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tant que l'utilisateur n'est pas entré avec un terrain (page « Mes terrains ») dont le justificatif
 * a été envoyé, les pages de travail le renvoient vers ce choix : personne ne continue sans terrain.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -10)]
final class ChoixTerrainListener
{
    private const PAGES = ['app_home_user', 'app_formation_user'];

    public function __construct(private Security $security, private TerrainActif $terrainActif, private UrlGeneratorInterface $urls)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $route = $event->getRequest()->attributes->get('_route');
        if (!in_array($route, self::PAGES, true)) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur || $user instanceof Admin) {
            return;
        }
        if ($route === 'app_formation_user' && $user->isFormateur()) {
            return; // un formateur voit les formations même sans terrain
        }
        // il faut entrer avec un terrain dont le justificatif a été envoyé (pas de navigation sans terrain)
        $t = $this->terrainActif->get();
        if (!$t || !$t->isUtilisable()) {
            $event->setResponse(new RedirectResponse($this->urls->generate('app_choix_terrain')));
        }
    }
}

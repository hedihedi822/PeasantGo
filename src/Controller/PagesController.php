<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Pages en préparation (titre + présentation courte), ouvertes à tous. */
final class PagesController extends AbstractController
{
    public const PAGES = [
        'projets' => ['Grands projets agricoles', 'fa-project-diagram', 'Les projets qui rassemblent l\'État, les associations, le syndicat et les agriculteurs.'],
        'bonification' => ['Bonification des terres', 'fa-tractor', 'Remettre en valeur les terres abandonnées, salées ou dégradées.'],
        'recrutement' => ['Recrutement et experts agricoles', 'fa-user-friends', 'Trouver des ouvriers agricoles, mais aussi un ingénieur agronome, un vétérinaire ou un technicien pour une mission ponctuelle (une visite, un diagnostic, un traitement) — ou proposer vos propres services.'],
        'marketplace' => ['Marketplace agricole', 'fa-store', 'Acheter et vendre produits agricoles, intrants et matériel.'],
        'tourisme' => ['Tourisme agricole', 'fa-campground', 'Fermes ouvertes, séjours à la campagne et produits du terroir.'],
        'actualites' => ['Actualités agricoles', 'fa-newspaper', 'Les nouvelles de l\'agriculture en Tunisie et dans le monde.'],
        'syndicat' => ['Syndicat des agriculteurs', 'fa-users', 'L\'espace du syndicat : annonces, services et défense des agriculteurs.'],
    ];

    #[Route('/projets-agricoles', name: 'app_page_projets')]
    public function projets(): Response { return $this->page('projets'); }

    #[Route('/bonification-des-terres', name: 'app_page_bonification')]
    public function bonification(): Response { return $this->page('bonification'); }

    #[Route('/recrutement', name: 'app_page_recrutement')]
    public function recrutement(): Response { return $this->page('recrutement'); }

    #[Route('/marketplace', name: 'app_page_marketplace')]
    public function marketplace(): Response { return $this->page('marketplace'); }

    #[Route('/tourisme-agricole', name: 'app_page_tourisme')]
    public function tourisme(): Response { return $this->page('tourisme'); }

    #[Route('/actualites', name: 'app_page_actualites')]
    public function actualites(): Response { return $this->page('actualites'); }

    #[Route('/syndicat', name: 'app_page_syndicat')]
    public function syndicat(): Response { return $this->page('syndicat'); }

    private function page(string $cle): Response
    {
        [$titre, $icone, $intro] = self::PAGES[$cle];
        return $this->render('pages/' . $cle . '.html.twig', ['cle' => $cle, 'titre' => $titre, 'icone' => $icone, 'intro' => $intro]);
    }
}

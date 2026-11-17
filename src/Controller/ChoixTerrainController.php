<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\CodeTerrain;
use App\Entity\Utilisateur;
use App\Repository\EstimationTerrainRepository;
use App\Terrain\TerrainActif;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Page entre la connexion et l'accueil : choix du terrain + menu du compte. */
final class ChoixTerrainController extends AbstractController
{
    #[Route('/mes-terrains', name: 'app_choix_terrain', methods: ['GET'])]
    public function index(TerrainActif $terrainActif, EstimationTerrainRepository $estimations): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        if ($user instanceof Admin) {
            return $this->redirectToRoute('app_admin');
        }
        $terrains = $user->getTerrains()->toArray();

        return $this->render('choix_terrain/index.html.twig', [
            'terrains' => $terrains,
            'terrainsJson' => array_map([TerrainController::class, 'terrainToArray'], $terrains),
            'types_justificatifs' => CodeTerrain::JUSTIFICATIFS,
            'actif' => $terrainActif->get(),
            'meilleures' => $estimations->meilleuresParTerrain($terrains),
        ]);
    }

    #[Route('/mes-terrains/{id}/entrer', name: 'app_choix_terrain_entrer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function entrer(int $id, Request $request, TerrainActif $terrainActif, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('entrer-terrain', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée, réessayez.');
            return $this->redirectToRoute('app_choix_terrain');
        }
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $t = $em->find(CodeTerrain::class, $id);
        if (!$t || $t->getProprietaire() !== $user) {
            throw $this->createNotFoundException();
        }
        if (!$t->isUtilisable()) {
            $this->addFlash('warning', sprintf('Pour entrer avec « %s », envoyez d\'abord son justificatif (certificat de propriété ou contrat de location).', $t->getNom()));
            return $this->redirectToRoute('app_choix_terrain');
        }
        $terrainActif->choisir($t);

        return $this->redirectToRoute('app_home_user');
    }

    #[Route('/guide', name: 'app_guide', methods: ['GET'])]
    public function guide(): Response
    {
        return $this->render('guide/index.html.twig');
    }
}

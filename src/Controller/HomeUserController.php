<?php

namespace App\Controller;

use App\Entity\Favori;
use App\Entity\ProduitsAgricole;
use App\Entity\Utilisateur;
use App\Repository\FavoriRepository;
use App\Robot\Referentiel;
use App\Service\CatalogueService;
use App\Terrain\TerrainActif;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Accueil de l'utilisateur connecté : mêmes tableaux + favoris. */
final class HomeUserController extends AbstractController
{
    use JsonApiTrait;

    #[Route('/home/user', name: 'app_home_user', methods: ['GET'])]
    public function index(CatalogueService $catalogue, FavoriRepository $favoris, TerrainActif $terrainActif): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $ids = array_map(fn (Favori $f) => $f->getIdP(), $favoris->findBy(['utilisateur' => $user]));

        return $this->render('home_user/home_user.html.twig', [
            'tableaux' => $catalogue->getTableaux(),
            'favoris' => $ids,
            'terrain' => $terrainActif->get(),
            'types_cultures' => array_map(fn ($c) => $c['type'], Referentiel::CULTURES),
        ]);
    }

    /** Ajoute / retire un produit des favoris (étoile). */
    #[Route('/api/favoris/{idP}', name: 'api_favori_toggle', methods: ['POST'], requirements: ['idP' => '\d+'])]
    public function toggleFavori(int $idP, Request $request, FavoriRepository $favoris, EntityManagerInterface $em): JsonResponse
    {
        $this->assertSameOrigin($request);
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if (!$em->find(ProduitsAgricole::class, $idP)) {
            return $this->fail('Produit introuvable', 404);
        }

        $existant = $favoris->findOneBy(['utilisateur' => $user, 'idP' => $idP]);
        if ($existant) {
            $em->remove($existant);
            $actif = false;
        } else {
            $em->persist((new Favori())->setUtilisateur($user)->setIdP($idP));
            $actif = true;
        }
        $em->flush();

        return $this->ok(['favori' => $actif]);
    }
}

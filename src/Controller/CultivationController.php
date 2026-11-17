<?php

namespace App\Controller;

use App\Entity\Cultivation;
use App\Robot\Apprentissage;
use App\Entity\Utilisateur;
use App\Repository\CultivationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Cultures du terrain : format JSON commun et déclaration du résultat d'une culture
 * (fenêtre du terrain → 2. Mes résultats).
 */
final class CultivationController extends AbstractController
{
    use JsonApiTrait;

    public static function cultivationToArray(Cultivation $c): array
    {
        return [
            'id' => $c->getId(),
            'date' => $c->getCreatedAt()->format('Y-m-d'),
            'terrainId' => $c->getTerrain()->getCodeTerrain(),
            'terrain' => $c->getTerrain()->getNom(),
            'culture' => $c->getCultureNom(),
            'cle' => $c->getCultureCle(),
            'animal' => \App\Robot\Referentiel::estAnimal($c->getCultureCle()),
            'surface' => $c->getSurface(),
            'effectif' => $c->getEffectif(),
            'unite' => \App\Terrain\Recoltes::unite($c->getCultureCle())[0],
            'origine' => $c->getOrigine(),
            'debut' => $c->getDateDebut()->format('Y-m-d'),
            'fin' => $c->getDateFin()?->format('Y-m-d'),
            'statut' => $c->getStatut(),
            'statutLabel' => $c->getStatutLabel(),
            'actif' => $c->isActif(),
            'recolte' => $c->isRecolteDeclaree() ? [
                'quantite' => $c->getQuantiteRecoltee(), 'prixVente' => $c->getPrixVente(), 'coutReel' => $c->getCoutReel(),
                'date' => $c->getDateRecolte()?->format('Y-m-d'), 'resultat' => $c->getResultat(),
                'resultatLabel' => Cultivation::RESULTATS[$c->getResultat()] ?? null, 'rendement' => $c->getRendement(),
            ] : null,
        ];
    }

    /** Résultat d'une culture de cette saison (fenêtre du terrain → 2. Mes résultats). */
    #[Route('/api/cultivations/{id}/recolte', name: 'api_cultivation_recolte', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function recolte(int $id, Request $request, CultivationRepository $cultivations, EntityManagerInterface $em, Apprentissage $apprentissage, \App\Terrain\Recoltes $recoltes): JsonResponse
    {
        $this->assertSameOrigin($request);
        $c = $cultivations->findOneForUser($id, $this->user());
        if (!$c) {
            throw new NotFoundHttpException();
        }
        $d = $this->payload($request);
        if ($errors = $recoltes->declarer($c, $d)) {
            return $this->fail('Veuillez corriger les champs indiqués.', 422, $errors);
        }
        $em->flush();
        $apprentissage->apprendre();

        return $this->ok([
            'message' => 'Récolte enregistrée. Merci : le robot apprend de vos résultats.',
            'cultivation' => self::cultivationToArray($c),
            'terrain' => TerrainController::terrainToArray($c->getTerrain()),
        ]);
    }

    private function user(): Utilisateur
    {
        /** @var Utilisateur $u */
        $u = $this->getUser();
        return $u;
    }
}

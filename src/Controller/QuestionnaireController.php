<?php

namespace App\Controller;

use App\Entity\Cultivation;
use App\Robot\Apprentissage;
use App\Robot\Referentiel;
use App\Robot\Saisons;
use App\Satellite\ProfilsCultures;
use App\Terrain\Recoltes;
use App\Terrain\TerrainActif;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Fenêtre d'accueil du terrain actif, en deux parties :
 *  1. cette saison : ce que l'agriculteur pense cultiver / élever, et sur quelle surface (ou combien d'animaux) ;
 *  2. mes résultats : les cultures de cette saison arrivées en fin de cycle (récolte à déclarer, déjà pré-remplies),
 *     puis la saison passée (production, prix de vente, coûts, résultat).
 * La partie 1 alimente la progression de la saison (offre à venir), la partie 2 l'apprentissage du robot.
 */
#[Route('/api/terrain-actif/questionnaire')]
final class QuestionnaireController extends AbstractController
{
    use JsonApiTrait;

    public function __construct(private TerrainActif $actif, private EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'api_questionnaire', methods: ['GET'])]
    public function etat(Saisons $saisons): JsonResponse
    {
        $t = $this->actif->get();
        if (!$t) {
            return $this->fail('Choisissez d\'abord un terrain.', 409);
        }
        $campagne = self::campagne();
        $options = [];
        foreach (Referentiel::tout() as $cle => $r) {
            $s = $saisons->prochaine($cle, new \DateTime('today'));
            [$unite] = Recoltes::unite($cle);
            $options[] = [
                'cle' => $cle, 'nom' => $r['nom'], 'type' => $r['type'], 'animal' => Referentiel::estAnimal($cle),
                'uniteTaille' => $r['unite'] ?? 'ha', 'tailleDefaut' => $r['tailleDefaut'] ?? null,
                'uniteProduction' => $unite, 'cycle' => $r['cycle'],
                'debutConseille' => $s['debutCultivation']->format('Y-m-d'),
            ];
        }
        $prevues = $passees = $aDeclarer = [];
        $bientot = new \DateTime('+10 days');
        foreach ($t->getCultivations() as $c) {
            // cultures de cette saison arrivées (ou presque) à la fin de leur cycle : le résultat est à déclarer
            if ($c->getOrigine() !== 'historique' && $c->isActif() && !$c->isRecolteDeclaree() && $c->getDateFin() && $c->getDateFin() <= $bientot) {
                $aDeclarer[] = CultivationController::cultivationToArray($c);
            }
            if ($c->getOrigine() === 'plan' && $c->isActif() && $c->getCreatedAt() >= new \DateTime($campagne . '-09-01')) {
                $prevues[] = CultivationController::cultivationToArray($c);
            }
            if ($c->getOrigine() === 'historique') {
                $passees[] = CultivationController::cultivationToArray($c);
            }
        }
        return $this->ok([
            'campagne' => $campagne,
            'libelleCampagne' => $campagne . '/' . ($campagne + 1),
            'partie1' => $t->questionnaireFait($campagne, 1),
            'partie2' => $t->questionnaireFait($campagne, 2),
            'terrain' => TerrainController::terrainToArray($t),
            'options' => $options,
            'prevues' => $prevues,
            'passees' => $passees,
            'aDeclarer' => $aDeclarer,
            'canaux' => Cultivation::CANAUX,
            'postes' => Cultivation::POSTES_COUTS,
            'resultats' => Cultivation::RESULTATS,
        ]);
    }

    /** Partie 1 : { aucune?: bool, cultures: [ { cle, surface?, effectif?, debut? } ] } */
    #[Route('/1', name: 'api_questionnaire_1', methods: ['POST'])]
    public function partie1(Request $request): JsonResponse
    {
        $this->assertSameOrigin($request);
        $t = $this->actif->get();
        if (!$t) {
            return $this->fail('Choisissez d\'abord un terrain.', 409);
        }
        $d = $this->payload($request);
        $campagne = self::campagne();
        $lignes = (array) ($d['cultures'] ?? []);
        if (!$lignes && empty($d['aucune'])) {
            return $this->fail('Ajoutez au moins une culture ou un élevage, ou cochez « je n\'ai pas encore décidé ».');
        }

        // le plan de cette saison remplace le précédent (seulement ce qui n'a pas encore commencé)
        $anciens = array_filter($t->getCultivations()->toArray(), fn (Cultivation $c) => $c->getOrigine() === 'plan' && $c->getStatut() === 'planifie' && $c->getCreatedAt() >= new \DateTime($campagne . '-09-01'));
        $libre = $t->getSurface() - $t->getSurfaceUtilisee() + array_sum(array_map(fn (Cultivation $c) => $c->getSurface(), $anciens));
        $nouvelles = [];
        $total = 0.0;
        $errors = [];
        foreach ($lignes as $i => $l) {
            $cle = (string) ($l['cle'] ?? '');
            if (!Referentiel::existe($cle)) {
                $errors["cultures.$i.cle"] = 'Choisissez une culture ou un élevage.';
                continue;
            }
            $ref = Referentiel::get($cle);
            $animal = Referentiel::estAnimal($cle);
            $debut = Recoltes::date($l['debut'] ?? null) ?? new \DateTime('today');
            $c = (new Cultivation())->setTerrain($t)->setCultureCle($cle)->setCultureNom($ref['nom'])->setStatut('planifie')->setOrigine('plan')
                ->setDateDebut($debut)->setDateFin((clone $debut)->modify('+' . $ref['cycle'] . ' days'));
            if ($animal) {
                $n = (int) ($l['effectif'] ?? 0);
                if ($n < 1 || $n > 1000000) {
                    $errors["cultures.$i.effectif"] = sprintf('Nombre de %s invalide.', $ref['unite']);
                    continue;
                }
                $c->setEffectif($n)->setSurface(0);
            } else {
                $s = (float) str_replace(',', '.', (string) ($l['surface'] ?? 0));
                if ($s <= 0) {
                    $errors["cultures.$i.surface"] = 'Surface invalide.';
                    continue;
                }
                $c->setSurface(round($s, 2));
                $total += $s;
            }
            $nouvelles[] = $c;
        }
        if ($total > $libre + 0.001) {
            $errors['surface'] = sprintf('Les cultures prévues (%s ha) dépassent la surface libre du terrain (%s ha).', number_format($total, 2, ',', ' '), number_format(max(0, $libre), 2, ',', ' '));
        }
        if ($errors) {
            return $this->fail(reset($errors), 422, $errors);
        }

        foreach ($anciens as $c) {
            $t->getCultivations()->removeElement($c);
            $this->em->remove($c);
        }
        foreach ($nouvelles as $c) {
            $t->getCultivations()->add($c);
            $this->em->persist($c);
        }
        $t->marquerQuestionnaire($campagne, 1, $nouvelles ? (new \DateTime())->format('Y-m-d') : 'aucune');
        $this->em->flush();

        return $this->ok(['message' => $nouvelles
            ? sprintf('%d culture(s)/élevage(s) prévus enregistrés : le robot les met en tête de votre tableau.', count($nouvelles))
            : 'Noté : le robot vous propose les cultures les plus rentables.']);
    }

    /**
     * Partie 2 : { aucune?: bool, cultures: [ { cle, surface?|effectif?, irrigue, debut, date (récolte),
     *   quantite, prixVente, coutReel?, couts?: {poste: DT}, resultat, canal, remarque } ] }
     */
    #[Route('/2', name: 'api_questionnaire_2', methods: ['POST'])]
    public function partie2(Request $request, Recoltes $recoltes, Apprentissage $apprentissage): JsonResponse
    {
        $this->assertSameOrigin($request);
        $t = $this->actif->get();
        if (!$t) {
            return $this->fail('Choisissez d\'abord un terrain.', 409);
        }
        $d = $this->payload($request);
        $campagne = self::campagne();
        $lignes = (array) ($d['cultures'] ?? []);
        if (!$lignes && empty($d['aucune'])) {
            return $this->fail('Ajoutez ce que vous avez cultivé ou élevé la saison passée, ou cochez « rien la saison passée ».');
        }
        $limite = new \DateTime(($campagne - 2) . '-09-01');
        $creees = [];
        $errors = [];
        foreach ($lignes as $i => $l) {
            $cle = ProfilsCultures::depuisDeclaration((string) ($l['cle'] ?? ''), null) ?? (string) ($l['cle'] ?? '');
            if (!Referentiel::existe($cle)) {
                $errors["cultures.$i.cle"] = 'Choisissez une culture ou un élevage.';
                continue;
            }
            $ref = Referentiel::get($cle);
            $debut = Recoltes::date($l['debut'] ?? null);
            if (!$debut || $debut < $limite || $debut > new \DateTime('today')) {
                $errors["cultures.$i.debut"] = sprintf('Date de début invalide pour %s (saison %d/%d ou précédente).', $ref['nom'], $campagne - 1, $campagne);
                continue;
            }
            $c = (new Cultivation())->setTerrain($t)->setCultureCle($cle)->setCultureNom($ref['nom'])->setStatut('en_cours')->setOrigine('historique')
                ->setDateDebut($debut)->setIrrigue(isset($l['irrigue']) ? (bool) $l['irrigue'] : null);
            if (Referentiel::estAnimal($cle)) {
                $n = (int) ($l['effectif'] ?? 0);
                if ($n < 1) {
                    $errors["cultures.$i.effectif"] = sprintf('Nombre de %s invalide.', $ref['unite']);
                    continue;
                }
                $c->setEffectif($n)->setSurface(0);
            } else {
                $s = (float) str_replace(',', '.', (string) ($l['surface'] ?? 0));
                if ($s <= 0 || $s > $t->getSurface() * 1.001) {
                    $errors["cultures.$i.surface"] = sprintf('Surface invalide pour %s (au plus %s ha).', $ref['nom'], $t->getSurface());
                    continue;
                }
                $c->setSurface(round($s, 2));
            }
            $err = $recoltes->declarer($c, $l);
            foreach ($err as $k => $m) {
                $errors["cultures.$i.$k"] = $ref['nom'] . ' : ' . $m;
            }
            if (!$err) {
                $creees[] = $c;
            }
        }
        if ($errors) {
            return $this->fail(reset($errors), 422, $errors);
        }
        foreach ($creees as $c) {
            $t->getCultivations()->add($c);
            $this->em->persist($c);
        }
        $t->marquerQuestionnaire($campagne, 2, $creees ? (new \DateTime())->format('Y-m-d') : 'aucune');
        $this->em->flush();
        $r = $creees ? $apprentissage->apprendre() : null;

        return $this->ok(['message' => $creees
            ? sprintf('Merci ! %d résultat(s) de la saison passée enregistrés : le robot en tient compte pour vous et pour votre région.', count($creees))
            : 'Noté.', 'apprentissage' => $r]);
    }

    /** Année de début de la campagne agricole en cours (1er septembre). */
    public static function campagne(): int
    {
        return (int) ProfilsCultures::debutCampagne(new \DateTime())->format('Y');
    }
}

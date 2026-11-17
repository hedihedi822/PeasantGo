<?php

namespace App\Robot;

use App\Entity\Cultivation;
use App\Entity\EstimationTerrain;
use App\Entity\ModeleCalibration;
use App\Entity\Zone;
use App\Repository\ModeleCalibrationRepository;
use App\Satellite\ProfilsCultures;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Le robot apprend des récoltes déclarées par les agriculteurs.
 * Pour chaque récolte, on compare ce qui s'est réellement passé à ce que le robot avait estimé
 * (dernière estimation faite AVANT la création de la culture ; à défaut le rendement de référence) :
 *   rendement réel / estimé, prix de vente réel / estimé, coût réel / estimé, réussite ou échec.
 * Les rapports sont agrégés (moyenne géométrique, bornée) par culture et par gouvernorat, et au niveau national.
 * Ces facteurs corrigent ensuite toutes les estimations (avec un poids qui grandit avec le nombre de récoltes).
 */
class Apprentissage
{
    public function __construct(private EntityManagerInterface $em, private ModeleCalibrationRepository $calibrations, private EstimateurCout $couts)
    {
    }

    /** @return array{recoltes: int, calibrations: int} */
    public function apprendre(): array
    {
        $groupes = [];
        $recoltes = $this->em->createQuery('SELECT c, t FROM ' . Cultivation::class . ' c JOIN c.terrain t WHERE c.quantiteRecoltee IS NOT NULL')->getResult();

        foreach ($recoltes as $c) {
            /** @var Cultivation $c */
            $cle = ProfilsCultures::depuisDeclaration($c->getCultureCle(), $c->getCultureNom());
            if (!$cle || !Referentiel::existe($cle) || $c->getTaille() <= 0 || $c->getRendement() === null) {
                continue;
            }
            $t = $c->getTerrain();
            if ($t->getJustificatifStatut() === 'refuse') {
                continue; // terrain dont la propriété n'a pas été prouvée : ses chiffres ne comptent pas
            }
            $ref = Referentiel::get($cle);
            $animal = Referentiel::estAnimal($cle);
            $estim = $this->estimationAvant($c);
            $irrigue = $c->getIrrigue() ?? $t->isIrrigue();

            // Ce que le robot attendait : son estimation d'avant la culture, sinon le modèle de référence
            $rendEstime = $estim ? (float) ($estim->getDetails()['rendementHa'] ?? 0) : 0;
            if ($rendEstime <= 0) {
                $rendEstime = $animal ? $ref['production'] * (1 - $ref['mortalite']) * 0.88 : $ref['rendement'][$irrigue ? 1 : 0] * 0.8;
            }
            $prixEstime = $estim?->getPrix() ?: (float) $ref['prix'];
            $tailleEstim = $estim ? ($animal ? (float) ($estim->getDetails()['taille'] ?? 0) : $t->getSurface()) : 0;
            $coutEstimeUnite = ($estim && $tailleEstim > 0) ? $estim->getCout() / $tailleEstim
                : $this->couts->parHectare($cle, $t->getLocalite() ?? $t->getGouvernorat(), (int) ($c->getDateRecolte() ?? new \DateTime())->format('Y'), $irrigue, 0)['parHa'];

            $obs = [
                'reussie' => $c->getResultat() === 'reussie' || ($c->getResultat() === 'partielle' && $c->getRendement() >= 0.5 * $rendEstime),
                'rendement' => $c->getRendement(),
                'fRend' => self::borne($c->getRendement() / $rendEstime),
                'fPrix' => ($c->getPrixVente() && $prixEstime > 0) ? self::borne($c->getPrixVente() / $prixEstime) : null,
                'fCout' => ($c->getCoutReel() && $coutEstimeUnite > 0) ? self::borne($c->getCoutReel() / ($coutEstimeUnite * $c->getTaille())) : null,
            ];
            foreach ($t->getGouvernorat() ? [$t->getGouvernorat(), null] : [null] as $z) {
                $k = $cle . '|' . ($z?->getId() ?? 0);
                $groupes[$k]['cle'] = $cle;
                $groupes[$k]['zone'] = $z;
                $groupes[$k]['obs'][] = $obs;
            }
        }

        $n = 0;
        foreach ($groupes as $g) {
            $this->enregistrer($g['cle'], $g['zone'], $g['obs']);
            ++$n;
        }
        $this->em->flush();
        return ['recoltes' => count($recoltes), 'calibrations' => $n];
    }

    private function enregistrer(string $cle, ?Zone $zone, array $obs): void
    {
        $cal = $this->calibrations->findOneBy(['cultureCle' => $cle, 'zone' => $zone]);
        if (!$cal) {
            $cal = new ModeleCalibration($cle, $zone);
            $this->em->persist($cal);
        }
        $geo = function (array $vals): float {
            $vals = array_filter($vals, fn ($v) => $v !== null && $v > 0);
            return $vals ? exp(array_sum(array_map('log', $vals)) / count($vals)) : 1.0;
        };
        $prix = array_filter(array_column($obs, 'fPrix'));
        $couts = array_filter(array_column($obs, 'fCout'));
        $rend = array_filter(array_column($obs, 'rendement'));
        $cal->mettreAJour(
            count($obs),
            count(array_filter($obs, fn ($o) => $o['reussie'])),
            $rend ? round(array_sum($rend) / count($rend), 3) : null,
            round($geo(array_column($obs, 'fRend')), 4),
            round($geo($prix), 4),
            count($prix),
            round($geo($couts), 4),
            count($couts),
        );
    }

    private function estimationAvant(Cultivation $c): ?EstimationTerrain
    {
        $cle = ProfilsCultures::depuisDeclaration($c->getCultureCle(), $c->getCultureNom());
        return $this->em->createQueryBuilder()
            ->select('e')->from(EstimationTerrain::class, 'e')
            ->andWhere('e.terrain = :t')->andWhere('e.cultureCle = :c')->andWhere('e.createdAt <= :d')
            ->setParameter('t', $c->getTerrain())->setParameter('c', $cle)->setParameter('d', $c->getCreatedAt())
            ->orderBy('e.createdAt', 'DESC')->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    private static function borne(float $v): float
    {
        return max(0.2, min(3.0, $v));
    }
}

<?php

namespace App\Repository;

use App\Entity\EstimationTerrain;
use App\Entity\CodeTerrain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EstimationTerrain> */
class EstimationTerrainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EstimationTerrain::class);
    }


    /** Lignes du dernier calcul du robot pour un terrain, meilleures d'abord. @return EstimationTerrain[] */
    public function dernierLot(CodeTerrain $t): array
    {
        $lot = $this->createQueryBuilder('e')->select('MAX(e.lot)')->andWhere('e.terrain = :t')->setParameter('t', $t)->getQuery()->getSingleScalarResult();
        if (!$lot) {
            return [];
        }
        return $this->findBy(['terrain' => $t, 'lot' => $lot], ['benefice' => 'DESC']);
    }

    /** @param CodeTerrain[] $terrains @return array<int, EstimationTerrain> code_terrain => meilleure culture */
    public function meilleuresParTerrain(array $terrains): array
    {
        $out = [];
        foreach ($terrains as $t) {
            $rows = $this->dernierLot($t);
            if ($rows) {
                $out[$t->getCodeTerrain()] = $rows[0];
            }
        }
        return $out;
    }

    /** Supprime les anciens calculs (garde les N derniers lots par terrain). */
    public function purger(CodeTerrain $t, int $garder = 10): void
    {
        $lots = array_column($this->createQueryBuilder('e')->select('DISTINCT e.lot')->andWhere('e.terrain = :t')->setParameter('t', $t)->orderBy('e.lot', 'DESC')->getQuery()->getScalarResult(), 'lot');
        $vieux = array_slice($lots, $garder);
        if ($vieux) {
            $this->createQueryBuilder('e')->delete()->andWhere('e.terrain = :t')->andWhere('e.lot IN (:l)')->setParameter('t', $t)->setParameter('l', $vieux)->getQuery()->execute();
        }
    }

    /**
     * Production que le robot a recommandée aux AUTRES terrains du gouvernorat (dernière analyse de chacun,
     * moins de 45 jours) : une partie des agriculteurs suivra le conseil, c'est donc de l'offre à venir.
     * @return array{terrains: int, tonnes: float}
     */
    public function offreRecommandee(string $cle, \App\Entity\Zone $gouvernorat, ?CodeTerrain $exclure): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id) AS n, COALESCE(SUM(e.productionEstimee), 0) AS tonnes')
            ->join('e.terrain', 't')
            ->andWhere('e.cultureCle = :c')->setParameter('c', $cle)
            ->andWhere('e.recommande = true')
            ->andWhere('t.gouvernorat = :g')->setParameter('g', $gouvernorat)
            ->andWhere("t.justificatifStatut IN ('en_attente', 'verifie_ia', 'verifie')")
            ->andWhere('e.createdAt > :d')->setParameter('d', new \DateTime('-45 days'))
            ->andWhere('e.lot = (SELECT MAX(e2.lot) FROM ' . EstimationTerrain::class . ' e2 WHERE e2.terrain = e.terrain)');
        if ($exclure && $exclure->getCodeTerrain()) {
            $qb->andWhere('t != :x')->setParameter('x', $exclure);
        }
        $r = $qb->getQuery()->getSingleResult();
        return ['terrains' => (int) $r['n'], 'tonnes' => (float) $r['tonnes']];
    }
}

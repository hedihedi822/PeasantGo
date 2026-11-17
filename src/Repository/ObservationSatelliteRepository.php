<?php

namespace App\Repository;

use App\Entity\CodeTerrain;
use App\Entity\ObservationSatellite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ObservationSatellite> */
class ObservationSatelliteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObservationSatellite::class);
    }

    /** @return ObservationSatellite[] */
    public function findPourTerrain(CodeTerrain $t, ?\DateTimeInterface $depuis = null): array
    {
        $qb = $this->createQueryBuilder('o')->andWhere('o.terrain = :t')->setParameter('t', $t)->orderBy('o.date', 'ASC');
        if ($depuis) {
            $qb->andWhere('o.date >= :d')->setParameter('d', $depuis);
        }
        return $qb->getQuery()->getResult();
    }

    public function findDerniereDate(CodeTerrain $t, string $source): ?\DateTimeInterface
    {
        $o = $this->findOneBy(['terrain' => $t, 'source' => $source], ['date' => 'DESC']);
        return $o?->getDate();
    }
}

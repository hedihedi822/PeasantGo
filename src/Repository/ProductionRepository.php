<?php

namespace App\Repository;

use App\Entity\Production;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Production::class);
    }

    public function save(Production $production, bool $flush = true): void
    {
        $this->getEntityManager()->persist($production);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Production[]
     */
    public function findByProductionRange(float $min, float $max): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.production BETWEEN :min AND :max')
            ->setParameter('min', $min)
            ->setParameter('max', $max)
            ->getQuery()
            ->getResult();
    }
}

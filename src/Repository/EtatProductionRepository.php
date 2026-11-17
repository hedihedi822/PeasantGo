<?php

namespace App\Repository;

use App\Entity\EtatProduction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EtatProductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EtatProduction::class);
    }

    public function add(EtatProduction $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EtatProduction $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByProductionStatus(bool $status): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.exceProduction = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getResult();
    }
}
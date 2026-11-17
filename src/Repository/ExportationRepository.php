<?php

namespace App\Repository;

use App\Entity\Exportation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ExportationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exportation::class);
    }

    public function add(Exportation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Exportation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySocieteExport(string $company): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.societeExport = :val')
            ->setParameter('val', $company)
            ->getQuery()
            ->getResult();
    }
}
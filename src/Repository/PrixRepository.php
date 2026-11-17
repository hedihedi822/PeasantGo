<?php

namespace App\Repository;

use App\Entity\Prix;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PrixRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prix::class);
    }

    public function save(Prix $prix, bool $flush = true): void
    {
        $this->getEntityManager()->persist($prix);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByIdS(int $idS): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.idS = :idS')
            ->setParameter('idS', $idS)
            ->getQuery()
            ->getResult();
    }
}

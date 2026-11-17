<?php

namespace App\Repository;

use App\Entity\Saison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SaisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Saison::class);
    }

    public function save(Saison $saison, bool $flush = true): void
    {
        $this->getEntityManager()->persist($saison);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Saison[]
     */
    public function findByAnnee(int $annee): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.annee = :annee')
            ->setParameter('annee', $annee)
            ->getQuery()
            ->getResult();
    }
}

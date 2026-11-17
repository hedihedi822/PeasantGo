<?php

namespace App\Repository;

use App\Entity\PrixMarche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PrixMarche> */
class PrixMarcheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrixMarche::class);
    }


    /** @return PrixMarche[] prix observés d'une culture depuis une date (toutes zones) */
    public function historique(string $cle, \DateTimeInterface $depuis): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.zone', 'z')->addSelect('z')
            ->andWhere('p.cultureCle = :c')->andWhere('p.date >= :d')
            ->setParameter('c', $cle)->setParameter('d', $depuis)
            ->orderBy('p.date', 'ASC')
            ->getQuery()->getResult();
    }
}

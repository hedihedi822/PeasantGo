<?php

namespace App\Repository;

use App\Entity\Cultivation;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Cultivation> */
class CultivationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cultivation::class);
    }

    public function findOneForUser(int $id, Utilisateur $user): ?Cultivation
    {
        return $this->createQueryBuilder('c')
            ->join('c.terrain', 't')
            ->andWhere('c.id = :id')
            ->andWhere('t.proprietaire = :u')
            ->setParameter('id', $id)
            ->setParameter('u', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

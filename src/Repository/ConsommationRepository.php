<?php

namespace App\Repository;

use App\Entity\Consommation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConsommationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consommation::class);
    }

    public function add(Consommation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Consommation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function updateConsommation(Consommation $consommation): int
    {
        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.consommation', ':cons')
            ->set('c.consommationParEtat', ':cpe')
            ->set('c.consommationParRegion', ':cpr')
            ->where('c.idP = :idP AND c.idS = :idS')
            ->setParameter('cons', $consommation->getConsommation())
            ->setParameter('cpe', $consommation->getConsommationParEtat())
            ->setParameter('cpr', $consommation->getConsommationParRegion())
            ->setParameter('idP', $consommation->getIdP())
            ->setParameter('idS', $consommation->getIdS())
            ->getQuery()
            ->execute();
    }

    public function deleteByCompositeId(int $idP, int $idS): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.idP = :idP AND c.idS = :idS')
            ->setParameter('idP', $idP)
            ->setParameter('idS', $idS)
            ->getQuery()
            ->execute();
    }

    public function findOneByCompositeId(int $idP, int $idS): ?Consommation
    {
        return $this->findOneBy([
            'idP' => $idP,
            'idS' => $idS
        ]);
    }
}

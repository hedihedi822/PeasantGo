<?php

namespace App\Repository;

use App\Entity\TauxReussite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TauxReussiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TauxReussite::class);
    }

    public function findByIds(int $idP, int $idS): ?TauxReussite
    {
        return $this->findOneBy(['idP' => $idP, 'idS' => $idS]);
    }

    public function insert(TauxReussite $taux): void
    {
        $this->getEntityManager()->persist($taux);
        $this->getEntityManager()->flush();
    }
}

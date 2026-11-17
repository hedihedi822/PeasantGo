<?php

namespace App\Repository;

use App\Entity\StatistiqueSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<StatistiqueSnapshot> */
class StatistiqueSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StatistiqueSnapshot::class);
    }

    public function findDernier(): ?StatistiqueSnapshot
    {
        return $this->findOneBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }
}

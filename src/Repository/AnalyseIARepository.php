<?php

namespace App\Repository;

use App\Entity\AnalyseIA;
use App\Entity\CodeTerrain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AnalyseIA> */
class AnalyseIARepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyseIA::class);
    }


    public function derniere(CodeTerrain $t, string $type): ?AnalyseIA
    {
        return $this->findOneBy(['terrain' => $t, 'type' => $type], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }
}

<?php

namespace App\Repository;

use App\Entity\CodeTerrain;
use App\Entity\PredictionCulture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PredictionCulture> */
class PredictionCultureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PredictionCulture::class);
    }

    public function findDerniere(CodeTerrain $t): ?PredictionCulture
    {
        return $this->findOneBy(['terrain' => $t], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }
}

<?php

namespace App\Repository;

use App\Entity\ZoneStat;
use App\Entity\Zone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ZoneStat> */
class ZoneStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZoneStat::class);
    }


    /** @return ZoneStat[] chiffres d'une culture dans une zone, les plus récents et les plus fiables d'abord */
    public function pour(Zone $zone, string $cle): array
    {
        $rows = $this->findBy(['zone' => $zone, 'cultureCle' => $cle], ['annee' => 'DESC']);
        usort($rows, fn (ZoneStat $a, ZoneStat $b) => [$b->getAnnee(), ZoneStat::PRIORITE[$b->getSource()] ?? 0] <=> [$a->getAnnee(), ZoneStat::PRIORITE[$a->getSource()] ?? 0]);
        return $rows;
    }
}

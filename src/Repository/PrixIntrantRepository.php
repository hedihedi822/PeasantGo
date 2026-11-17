<?php

namespace App\Repository;

use App\Entity\PrixIntrant;
use App\Entity\Zone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PrixIntrant> */
class PrixIntrantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrixIntrant::class);
    }


    /** Dernier prix connu d'un intrant : d'abord dans la zone (et ses parents), sinon n'importe où. */
    public function dernierPrix(string $intrant, ?Zone $zone = null): ?PrixIntrant
    {
        for ($z = $zone; $z; $z = $z->getParent()) {
            $p = $this->findOneBy(['intrant' => $intrant, 'zone' => $z], ['date' => 'DESC', 'id' => 'DESC']);
            if ($p) {
                return $p;
            }
        }
        return $this->findOneBy(['intrant' => $intrant], ['date' => 'DESC', 'id' => 'DESC']);
    }
}

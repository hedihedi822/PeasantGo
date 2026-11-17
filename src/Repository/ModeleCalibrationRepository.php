<?php

namespace App\Repository;

use App\Entity\ModeleCalibration;
use App\Entity\Zone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ModeleCalibration> */
class ModeleCalibrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModeleCalibration::class);
    }


    /** Calibration la plus précise disponible : zone, puis national (zone null). */
    public function pour(string $cle, ?Zone $zone): ?ModeleCalibration
    {
        if ($zone && ($c = $this->findOneBy(['cultureCle' => $cle, 'zone' => $zone])) && $c->getNbRecoltes() >= 2) {
            return $c;
        }
        return $this->findOneBy(['cultureCle' => $cle, 'zone' => null]);
    }
}

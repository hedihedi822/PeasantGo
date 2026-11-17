<?php

namespace App\Repository;

use App\Entity\Zone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Zone> */
class ZoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Zone::class);
    }


    public function findOneByCode(string $niveau, string $code): ?Zone
    {
        return $this->findOneBy(['niveau' => $niveau, 'code' => $code]);
    }

    public function monde(): ?Zone
    {
        return $this->findOneByCode('monde', 'WORLD');
    }

    public function tunisie(): ?Zone
    {
        return $this->findOneByCode('pays', 'TN');
    }

    /** @return Zone[] */
    public function parNiveau(string $niveau): array
    {
        return $this->findBy(['niveau' => $niveau], ['nom' => 'ASC']);
    }

    /** Zone du niveau donné la plus proche d'un point. */
    public function plusProche(string $niveau, float $lat, float $lon): ?Zone
    {
        $best = null;
        $bestD = INF;
        foreach ($this->parNiveau($niveau) as $z) {
            if ($z->getLatitude() === null) {
                continue;
            }
            $d = Zone::haversine($lat, $lon, $z->getLatitude(), $z->getLongitude());
            if ($d < $bestD) {
                $bestD = $d;
                $best = $z;
            }
        }
        return $best;
    }

    /** Gouvernorat dont le nom ressemble au texte (sans accents, insensible à la casse). */
    public function gouvernoratParNom(string $texte): ?Zone
    {
        $n = self::normaliser($texte);
        foreach ($this->parNiveau('gouvernorat') as $z) {
            $zn = self::normaliser($z->getNom());
            if ($zn !== '' && (str_contains($n, $zn) || str_contains($zn, $n))) {
                return $z;
            }
        }
        return null;
    }

    public static function normaliser(string $s): string
    {
        $s = \Symfony\Component\String\u($s)->ascii()->lower()->toString();
        return trim(preg_replace('/[^a-z0-9]+/', ' ', str_replace(['le ', 'la '], '', $s)));
    }
}

<?php

namespace App\Repository;

use App\Entity\TerreDesignee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method TerreDesignee|null find($id, $lockMode = null, $lockVersion = null)
 * @method TerreDesignee|null findOneBy(array $criteria, array $orderBy = null)
 * @method TerreDesignee[]    findAll()
 * @method TerreDesignee[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TerreDesigneeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TerreDesignee::class);
    }

    // Vous pouvez ajouter des méthodes personnalisées ici
    public function findByIdP(int $idP): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.idP = :idP')
            ->setParameter('idP', $idP)
            ->orderBy('t.idS', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

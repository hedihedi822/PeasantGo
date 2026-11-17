<?php

namespace App\Repository;

use App\Entity\Admin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Admin>
 */
class AdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Admin::class);
    }

    // Exemple de méthode personnalisée pour trouver un admin par CIN
    public function findOneByCin(int $cin): ?Admin
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.cin = :cin')
            ->setParameter('cin', $cin)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Exemple de méthode pour filtrer par nom et prénom
    public function findByNomAndPrenom(string $nom, string $prenom): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.nom = :nom')
            ->andWhere('a.prenom = :prenom')
            ->setParameter('nom', $nom)
            ->setParameter('prenom', $prenom)
            ->getQuery()
            ->getResult();
    }

    // Exemple de méthode pour compter le nombre d'admins
    public function countAdmins(): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

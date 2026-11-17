<?php

namespace App\Repository;

use App\Entity\RegularUtilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegularUtilisateur>
 */
class RegularUtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegularUtilisateur::class);
    }

    // Vous pouvez ajouter ici vos méthodes personnalisées pour RegularUtilisateur
    // Par exemple :

    /**
     * Trouve un utilisateur par son CIN
     */
    public function findByCin(int $cin): ?RegularUtilisateur
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.cin = :cin')
            ->setParameter('cin', $cin)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve des utilisateurs par nom et prénom
     */
    public function findByNomPrenom(string $nom, string $prenom): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.nom = :nom')
            ->andWhere('r.prenom = :prenom')
            ->setParameter('nom', $nom)
            ->setParameter('prenom', $prenom)
            ->getQuery()
            ->getResult();
    }
}
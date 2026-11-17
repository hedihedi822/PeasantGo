<?php

namespace App\Repository;

use App\Entity\Societe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Societe>
 */
class SocieteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Societe::class);
    }

    // Trouve une société par son code fiscal (unique)
    public function findByCodeFiscal(string $codeFiscal): ?Societe
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.codeFiscal = :codeFiscal')
            ->setParameter('codeFiscal', $codeFiscal)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Trouve une société par son numéro SIRET (unique)
    public function findByNumeroSiret(string $numeroSiret): ?Societe
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.numeroSiret = :numeroSiret')
            ->setParameter('numeroSiret', $numeroSiret)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Trouve une société par son code société (unique)
    public function findByCodeSociete(string $codeSociete): ?Societe
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.codeSociete = :codeSociete')
            ->setParameter('codeSociete', $codeSociete)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Recherche des sociétés par nom (avec LIKE pour une recherche partielle)
    public function searchByNom(string $nom): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.nomSociete LIKE :nom')
            ->setParameter('nom', '%' . $nom . '%')
            ->getQuery()
            ->getResult();
    }

    // Compte le nombre total de sociétés
    public function countSocietes(): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Trouve des sociétés avec pagination (pour les listes)
    public function findPaginated(int $page, int $limit): array
    {
        return $this->createQueryBuilder('s')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
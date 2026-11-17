<?php

namespace App\Repository;

use App\Entity\ProduitsAgricole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProduitsAgricole>
 *
 * @method ProduitsAgricole|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProduitsAgricole|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProduitsAgricole[]    findAll()
 * @method ProduitsAgricole[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProduitsAgricoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProduitsAgricole::class);
    }

    // 🔍 Exemple de méthode personnalisée
    public function findByCategorie(string $categorie): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.categorie = :categorie')
            ->setParameter('categorie', $categorie)
            ->orderBy('p.type', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByProductionExcessive(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exceProduction = :val')
            ->setParameter('val', true)
            ->getQuery()
            ->getResult();
    }
}

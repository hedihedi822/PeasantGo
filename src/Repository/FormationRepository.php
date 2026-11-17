<?php
// src/Repository/FormationRepository.php

namespace App\Repository;

use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    public function save(Formation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Formation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** Formations actives, filtrées par mot-clé (titre, description, type, sous-type, catégorie). */
    public function createSearchQuery(string $q = ''): \Doctrine\ORM\Query
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.active = true')
            ->orderBy('f.createdAt', 'DESC')
            ->addOrderBy('f.id', 'DESC');

        if ($q !== '') {
            $qb->andWhere('LOWER(f.title) LIKE :q OR LOWER(f.description) LIKE :q OR LOWER(f.type) LIKE :q OR LOWER(f.sousType) LIKE :q OR LOWER(f.categorie) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        return $qb->getQuery();
    }
}
